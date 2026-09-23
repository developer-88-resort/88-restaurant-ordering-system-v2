---
description: Run the kitchen printer bridge for the OFFICE network (printer at 192.168.1.50)
---

Start (or restart) the thermal printer bridge on this PC for the **office** site,
where the printer holds `192.168.1.50`.

Do this:

1. **Check the site.** Open a TCP connection to `192.168.1.50:9100` (3s timeout).
   - If it answers, carry on.
   - If it does not, also try `192.168.0.50:9100` (the restaurant printer). If
     THAT answers, stop and tell the user they look like they are at the
     restaurant and should run `/resto` instead. If neither answers, report it
     plainly: the printer is off, asleep, or this PC is on a different network —
     do not start the bridge.
2. **Only one bridge may run**, or a job claimed here can't be printed there.
   Check with `Get-Process -Name php` (PowerShell — `tasklist` has lied about
   this before) and stop any bridge already running on this machine, including
   one started by an earlier session (`TaskStop`).
3. **Start it in the background**, pinned to this site's printer so it never
   waits on the other address first:

   ```
   THERMAL_PRINTER_HOST=192.168.1.50 php artisan printer:bridge
   ```

4. **Confirm it is alive** — read the first lines of its output ("Printer bridge
   started. Polling …"), and report to the user:
   - which printer address it is using,
   - the server it polls (`PRINTER_BRIDGE_API_URL` in `.env`),
   - how many copies one Direct Print press prints and the pause between them
     (`config('printing.kitchen_slip_copies')` and `copy_pause_seconds`).

Remind them, only if it is true, that the bridge dies when this PC shuts down or
the session ends, so `/office` has to be run again after a reboot.
