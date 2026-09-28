import Swal from 'sweetalert2';

// Staff at the grill kept sending half-finished orders to the kitchen with one
// stray tap on Place Order, so the button now opens a summary of exactly what
// would be sent — location, slip, lines, total, notes — and only a deliberate
// "Yes" submits.
//
// Called from resources/views/orders/create.blade.php as
// window.confirmOrderBeforePlacing(this), where `this` is that page's Alpine
// component: the dialog's wording and figures all come from there, so nothing
// is duplicated between the summary panel and this dialog. Resolves true only
// when the person confirms.
export function confirmOrderBeforePlacing(order) {
    const escape = (value) =>
        String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;',
        })[char]);

    const lines = order.cart
        .map(
            (line) =>
                `<li class="flex items-start justify-between gap-3 py-1">
                    <span>${line.qty}&times; ${escape(line.name)}</span>
                    <span class="shrink-0 font-semibold">${order.formatMoney(line.price * line.qty)}</span>
                </li>`,
        )
        .join('');

    // The slip line only means something for a table; take-out has none.
    const where = [order.locationLabel, order.spaceId ? order.slipChoiceLabel : '', order.paxLabel]
        .filter(Boolean)
        .map((part) => escape(part))
        .join(' &middot; ');

    const notes = (document.getElementById('notes')?.value ?? '').trim();

    return Swal.fire({
        title: order.confirmTitleLabel,
        html: `<div class="text-left text-sm text-[#302521]">
                <p class="text-xs font-semibold uppercase tracking-wide text-[#8A7B6D]">${where}</p>
                <ul class="mt-2 divide-y divide-[#EFE6DA] border-y border-[#EFE6DA]">${lines}</ul>
                <p class="mt-3 flex items-center justify-between text-base font-bold">
                    <span>${escape(order.totalLabel)}</span>
                    <span>${order.formatMoney(order.total)}</span>
                </p>
                ${notes ? `<p class="mt-3 rounded-lg bg-[#F5EFE7] px-3 py-2 text-xs text-[#5C4F49]">${escape(notes)}</p>` : ''}
            </div>`,
        showCancelButton: true,
        // Cancel sits where the thumb lands and holds the focus: the whole
        // point is that placing the order takes a deliberate second tap.
        reverseButtons: true,
        focusCancel: true,
        confirmButtonText: order.confirmActionLabel,
        cancelButtonText: order.keepEditingLabel,
        confirmButtonColor: '#8A3330',
        cancelButtonColor: '#766860',
        width: 460,
    }).then((result) => result.isConfirmed === true);
}
