function formatDateForApi(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    const seconds = String(date.getSeconds()).padStart(2, '0');
    
    return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
}
function dateDiffed(dayDiff) {
    const today = new Date();
    today.setDate(today.getDate() + dayDiff);
    return formatDateForApi(today);
}

function formatTimeFromDate(date) {
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

export {
    formatDateForApi,
    dateDiffed,
}