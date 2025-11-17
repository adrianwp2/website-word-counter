(function ($) {
    "use strict";

    // Function to update combined total (Content + PDFs)
    function updateCombinedTotal() {
        var $combinedDisplay = $("#wwc-combined-count");
        var $contentDisplay = $("#wwc-total-count");
        var $pdfDisplay = $("#wwc-pdf-count");

        var contentText = $contentDisplay.text().replace(/,/g, "");
        var pdfText = $pdfDisplay.text().replace(/,/g, "");

        var contentCount = 0;
        var pdfCount = 0;

        // Parse content count
        if (contentText && !isNaN(parseInt(contentText))) {
            contentCount = parseInt(contentText);
        }

        // Parse PDF count
        if (pdfText && !isNaN(parseInt(pdfText))) {
            pdfCount = parseInt(pdfText);
        }

        var combined = contentCount + pdfCount;
        if (combined > 0) {
            $combinedDisplay.text(combined.toLocaleString());
        } else {
            $combinedDisplay.text("Not calculated yet");
        }
    }

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

                    // Update combined total after content refresh
                    updateCombinedTotal();
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

    function triggerPdfCountRefresh() {
        if (typeof WebsiteWordCounter === "undefined") {
            return;
        }

        var $button = $("#wwc-refresh-pdf");
        var $countDisplay = $("#wwc-pdf-count");
        var totalWords = 0;
        var pdfList = [];
        var chunkSize = 5;
        var currentIndex = 0;

        // Disable button during processing
        $button.prop("disabled", true).text("Getting PDF list...");

        // Step 1: Get list of PDFs
        $.post(WebsiteWordCounter.ajax_url, {
            action: "website_word_counter_get_pdf_list",
            nonce: WebsiteWordCounter.nonce,
        })
            .done(function (res) {
                console.log("PDF List Response:", res);

                if (res.success && res.data && res.data.pdf_list) {
                    pdfList = res.data.pdf_list;
                    var totalPdfs = pdfList.length;

                    if (totalPdfs === 0) {
                        $countDisplay.text("0");
                        $button
                            .prop("disabled", false)
                            .text("Refresh PDF Count");
                        alert("No PDF attachments found in the media library.");
                        return;
                    }

                    $button.text("Processing PDFs (0/" + totalPdfs + ")...");
                    totalWords = 0;
                    currentIndex = 0;

                    // Step 2: Process PDFs in chunks
                    processNextChunk();
                } else {
                    console.error("Error getting PDF list:", res);
                    alert(
                        "Error: " +
                            (res.data && res.data.message
                                ? res.data.message
                                : "Failed to get PDF list")
                    );
                    $button.prop("disabled", false).text("Refresh PDF Count");
                }
            })
            .fail(function (xhr, status, error) {
                console.error("PDF List AJAX error:", {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                });
                alert(
                    "Error getting PDF list: " +
                        error +
                        ". Please check the browser console for details."
                );
                $button.prop("disabled", false).text("Refresh PDF Count");
            });

        // Process PDFs in chunks
        function processNextChunk() {
            if (currentIndex >= pdfList.length) {
                // All PDFs processed, save the total
                savePdfCount();
                return;
            }

            // Get next chunk of PDF IDs
            var chunk = pdfList.slice(currentIndex, currentIndex + chunkSize);
            var processedCount = currentIndex + chunk.length;
            var totalPdfs = pdfList.length;

            $button.text(
                "Processing PDFs (" + processedCount + "/" + totalPdfs + ")..."
            );

            // Process this chunk
            $.post(WebsiteWordCounter.ajax_url, {
                action: "website_word_counter_process_pdf_batch",
                nonce: WebsiteWordCounter.nonce,
                pdf_ids: chunk,
            })
                .done(function (res) {
                    console.log("Batch Response:", res);

                    if (
                        res.success &&
                        res.data &&
                        res.data.batch_words !== undefined
                    ) {
                        totalWords += res.data.batch_words;
                        $countDisplay.text(totalWords.toLocaleString());

                        // Update combined total in real-time as batches complete
                        updateCombinedTotal();

                        // Move to next chunk
                        currentIndex += chunkSize;

                        // Process next chunk after a short delay to prevent overwhelming the server
                        setTimeout(processNextChunk, 100);
                    } else {
                        console.error("Error processing batch:", res);
                        // Continue with next chunk even if this one failed
                        currentIndex += chunkSize;
                        setTimeout(processNextChunk, 100);
                    }
                })
                .fail(function (xhr, status, error) {
                    console.error("Batch AJAX error:", {
                        status: status,
                        error: error,
                        responseText: xhr.responseText,
                    });
                    // Continue with next chunk even if this one failed
                    currentIndex += chunkSize;
                    setTimeout(processNextChunk, 100);
                });
        }

        // Save final PDF count
        function savePdfCount() {
            $.post(WebsiteWordCounter.ajax_url, {
                action: "website_word_counter_save_pdf_count",
                nonce: WebsiteWordCounter.nonce,
                total_words: totalWords,
            })
                .done(function (res) {
                    console.log("Save PDF Count Response:", res);
                    if (res.success) {
                        $countDisplay.text(totalWords.toLocaleString());
                        $button.text("Refresh PDF Count");

                        // Update combined total after PDF refresh completes
                        updateCombinedTotal();
                    }
                })
                .fail(function (xhr, status, error) {
                    console.error("Save PDF Count error:", error);
                })
                .always(function () {
                    // Re-enable button
                    $button.prop("disabled", false).text("Refresh PDF Count");
                });
        }
    }

    // Trigger on button clicks
    $(function () {
        $("#wwc-refresh").on("click", function (e) {
            e.preventDefault();
            triggerWordCountRefresh();
        });

        $("#wwc-refresh-pdf").on("click", function (e) {
            e.preventDefault();
            triggerPdfCountRefresh();
        });
    });
})(jQuery);
