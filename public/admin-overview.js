function download() {
    var query = "data-admin-payments.php?format=download";
    query += "&end=" + formatDate(getNativeDate("#to"));
    window.location.href = window.location.href.replace(/[^/]*$/, "") + query;
    return false;
}

$(function() {
    $("#tabs > ul").tabs();

    setNativeDate("#to", new Date());
    $("#download").click(download);

    show("#message");
});
