# Kitchen printer bridge

The live site runs on a VPS that has no route to the resort's thermal printer,
so Direct Print only queues a job. A small process on a PC **inside the same
network as the printer** picks those jobs up and prints them. That process is
the bridge.

One bridge at a time. Two of them polling at once means whichever claims a job
first prints it — and if that one can't reach the printer, the slip is lost.

## Running it

In Claude Code, on the PC next to the printer:

- `/resto` — restaurant network, printer at `192.168.0.50`
- `/office` — office network, printer at `192.168.1.50`

Each command checks that the printer answers, stops any bridge already running
on that machine, starts a new one pinned to that site's printer, and reports
what it is polling. If you run the wrong one it will say so instead of starting.

By hand, it is:

```bash
THERMAL_PRINTER_HOST=192.168.0.50 php artisan printer:bridge
```

The bridge dies when the PC shuts down or the terminal closes — start it again
after a reboot. Nothing is lost while it is down: jobs wait in the queue and
print when it comes back.

## What that PC needs

- The project code (this repo) and `vendor/` — `composer install`
- PHP 8.2+ with the GD extension
- Google Chrome (the slip is printed as a screenshot of the same HTML the
  browser Print button uses, so both come out identical)
- A `.env` with:

  ```
  PRINTER_BRIDGE_API_URL=https://88hotspringresort.online
  PRINTER_BRIDGE_TOKEN=<the same token as the server>
  THERMAL_PRINTER_HOST=192.168.1.50,192.168.0.50
  THERMAL_PRINTER_PORT=9100
  CACHE_STORE=file
  SESSION_DRIVER=file
  QUEUE_CONNECTION=sync
  ```

No database, web server or Node is needed there — only the bridge runs.

## Updating it

```bash
git pull
composer install --no-dev
```

then start the bridge again with `/resto` or `/office`. Restarting matters: it
is a long-running process and keeps the printing code it loaded at startup, so
a pull alone changes nothing until it is restarted.

## How copies work

One press of Direct Print prints **three copies** of the slip: one, a three
second pause, the next, another pause, the last. Each copy is cut on its own so
it can be taken off the printer.

The server decides this per job (`KITCHEN_SLIP_COPIES` and
`SLIP_COPY_PAUSE_SECONDS` in `config/printing.php`) and sends it along with the
job, so every bridge prints the same thing. Set the pause to `0` and the copies
come out instead as one continuous slip with a tear line between them.

The button on the Kitchen Display stays locked from the press until all copies
are done or the printer reports a failure, and pressing it again meanwhile
hands back the same job rather than queueing another.

## When Direct Print "doesn't work"

Check these in order — each has been the real cause at least once:

1. **The bridge isn't running.** Most common by far. Check `Get-Process -Name php`
   (not `tasklist`, which has lied about this) and run `/resto` or `/office`.
2. **Wrong site.** The printer keeps a static IP that differs per location
   (`.0.50` at the restaurant, `.1.50` at the office).
3. **Standby Mode.** The printer's Ethernet energy-saving setting makes it
   accept the connection and the bytes, then never print. It must stay
   **disabled** — printer web config, Configuration → Network → Advanced.
4. **A very long slip.** Tall slips are split into bands that each fit one
   graphics command; an older build sent one oversized command, printed nothing,
   and then ignored every later job until the printer was reset.
