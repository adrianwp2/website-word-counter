(function ($) {
    "use strict";

    function triggerWordCountRefresh() {
        if (typeof WebsiteWordCounter === "undefined") {
            return;
        }

        var $button = $("#wwc-refresh");
        var $countDisplay = $("#wwc-total-count");
        var $breakdown = $("#wwc-post-type-breakdown");

        // Disable button during request
        $button.prop("disabled", true).text("Calculating...");

        return $.post(WebsiteWordCounter.ajax_url, {
            action: "website_word_counter_refresh",
            nonce: WebsiteWordCounter.nonce,
        })
            .done(function (res) {
                if (
                    res.success &&
                    res.data &&
                    res.data.total_words !== undefined
                ) {
                    // Update the total display with formatted number
                    $countDisplay.text(res.data.total_words.toLocaleString());

                    // Update the breakdown table
                    if (
                        res.data.by_post_type &&
                        Object.keys(res.data.by_post_type).length > 0
                    ) {
                        var html = "";
                        for (var postType in res.data.by_post_type) {
                            if (
                                res.data.by_post_type.hasOwnProperty(postType)
                            ) {
                                var postTypeData =
                                    res.data.by_post_type[postType];
                                var label = postTypeData.label || postType;
                                var count = postTypeData.count || postTypeData;
                                html += "<tr>";
                                html += "<td>" + label + "</td>";
                                html +=
                                    "<td class='wwc-count-" +
                                    postType +
                                    "'>" +
                                    count.toLocaleString() +
                                    "</td>";
                                html += "</tr>";
                            }
                        }
                        $breakdown.html(html);
                    }
                } else {
                    console.error("Error:", res);
                }
            })
            .fail(function (xhr) {
                console.error("AJAX error:", xhr);
                alert("Error calculating word count. Please try again.");
            })
            .always(function () {
                // Re-enable button
                $button.prop("disabled", false).text("Refresh Count");
            });
    }

    // Example trigger: on button click with id #wwc-refresh
    $(function () {
        $("#wwc-refresh").on("click", function (e) {
            e.preventDefault();
            triggerWordCountRefresh();
        });
    });
})(jQuery);
