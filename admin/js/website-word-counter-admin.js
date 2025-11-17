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
                console.log("AJAX Response:", res);

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
                    } else {
                        // Clear table if no breakdown data
                        $breakdown.html(
                            "<tr><td colspan='2'>No data available</td></tr>"
                        );
                    }
                } else {
                    console.error("Error in response:", res);
                    alert(
                        "Error: " +
                            (res.data && res.data.message
                                ? res.data.message
                                : "Unknown error occurred")
                    );
                }
            })
            .fail(function (xhr, status, error) {
                console.error("AJAX error:", {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                });
                alert(
                    "Error calculating word count: " +
                        error +
                        ". Please check the browser console for details."
                );
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
