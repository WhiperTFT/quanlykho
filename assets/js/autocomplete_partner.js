// File: autocomplete_partner.js

$(document).ready(function () {
    $("input[name='partner_name']").autocomplete({
        source: "search_partner.php",
        minLength: 1,
        delay: 200
    });
});
