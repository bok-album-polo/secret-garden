document.addEventListener('DOMContentLoaded', function () {
    const dateCells = document.querySelectorAll('.utc-date');

    dateCells.forEach(cell => {
        const utcDate = cell.getAttribute('data-utc');
        if (!utcDate) return;

        const date = new Date(utcDate);

        // Check for an invalid date
        if (isNaN(date.getTime())) {
            console.warn('Invalid date:', utcDate);
            return;
        }

        // Use toLocaleString for better formatting and localization
        //Sweden uses the ISO 8601 date format 2026-01-17 14:30
        cell.textContent = date.toLocaleString('sv-SE', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        }).replace(',', '');
    });
});