/**
 * Batch Variation Saving Module
 * Handles saving product variants in chunks to avoid server timeout issues
 */

const BatchVariationSaver = (function() {
    // Configuration
    const BATCH_SIZE = 3; // Number of variants to save per batch
    const RETRY_ATTEMPTS = 3;
    const RETRY_DELAY = 1000; // ms

    let progressModal = null;
    let progressBar = null;
    let progressText = null;
    let currentProductId = null;
    let batchSaveUrl = '';
    let clearVariationsUrl = '';

    /**
     * Initialize the batch saver with routes
     */
    function init(config) {
        batchSaveUrl = config.batchSaveUrl || '';
        clearVariationsUrl = config.clearVariationsUrl || '';
        currentProductId = config.productId || null;
        createProgressModal();
    }

    /**
     * Create progress modal HTML
     */
    function createProgressModal() {
        if (document.getElementById('batch-variation-progress-modal')) {
            return;
        }

        const modalHtml = `
            <div class="modal fade" id="batch-variation-progress-modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="tio-upload me-2"></i>
                                Saving Variations
                            </h5>
                        </div>
                        <div class="modal-body">
                            <div class="text-center mb-3">
                                <div id="batch-progress-text" class="mb-2">Preparing...</div>
                                <div class="progress" style="height: 25px;">
                                    <div id="batch-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated"
                                         role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                        0%
                                    </div>
                                </div>
                            </div>
                            <div id="batch-status-details" class="small text-muted text-center"></div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);
        progressModal = new bootstrap.Modal(document.getElementById('batch-variation-progress-modal'));
        progressBar = document.getElementById('batch-progress-bar');
        progressText = document.getElementById('batch-progress-text');
    }

    /**
     * Update progress UI
     */
    function updateProgress(current, total, message) {
        const percent = Math.round((current / total) * 100);
        progressBar.style.width = percent + '%';
        progressBar.textContent = percent + '%';
        progressBar.setAttribute('aria-valuenow', percent);
        progressText.textContent = message || `Saving batch ${current} of ${total}...`;
    }

    /**
     * Extract variations data from the form
     */
    function extractVariationsFromForm() {
        const variations = [];
        const typeInputs = document.querySelectorAll('input[name="type[]"]');

        typeInputs.forEach((typeInput) => {
            const type = typeInput.value;
            const fieldName = type.replace(/[ .]/g, '_');

            const priceInput = document.querySelector(`input[name="price_${fieldName}"]`);
            const skuInput = document.querySelector(`input[name="sku_${fieldName}"]`);
            const qtyInput = document.querySelector(`input[name="qty_${fieldName}"]`);
            const discountInput = document.querySelector(`input[name="discount_${fieldName}"]`);
            const discountTypeInput = document.querySelector(`input[name="discount_type_${fieldName}"]`);
            const sortOrderInput = document.querySelector(`input[name="sort_order_${fieldName}"]`);
            const buyingPriceInput = document.querySelector(`input[name="buying_price_${fieldName}"]`);

            variations.push({
                type: type,
                price: priceInput ? parseFloat(priceInput.value) || 0 : 0,
                sku: skuInput ? skuInput.value : '',
                qty: qtyInput ? parseInt(qtyInput.value) || 1 : 1,
                discount: discountInput ? parseFloat(discountInput.value) || 0 : 0,
                discount_type: discountTypeInput ? discountTypeInput.value : 'flat',
                sort_order: sortOrderInput ? parseInt(sortOrderInput.value) || 999 : 999,
                buying_price: buyingPriceInput && buyingPriceInput.value !== '' ? parseFloat(buyingPriceInput.value) || null : null
            });
        });

        return variations;
    }

    /**
     * Split variations into batches
     */
    function splitIntoBatches(variations, batchSize) {
        const batches = [];
        for (let i = 0; i < variations.length; i += batchSize) {
            batches.push(variations.slice(i, i + batchSize));
        }
        return batches;
    }

    /**
     * Send a single batch with retry logic
     */
    async function sendBatch(batch, batchIndex, totalBatches, isFirst, isLast) {
        let attempts = 0;

        while (attempts < RETRY_ATTEMPTS) {
            try {
                const response = await fetch(batchSaveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        product_id: currentProductId,
                        variations: batch,
                        batch_index: batchIndex,
                        is_first_batch: isFirst,
                        is_last_batch: isLast
                    })
                });

                const data = await response.json();

                if (data.success) {
                    return { success: true, data: data };
                } else {
                    throw new Error(data.message || 'Unknown error');
                }
            } catch (error) {
                attempts++;
                if (attempts >= RETRY_ATTEMPTS) {
                    return { success: false, error: error.message, batchIndex: batchIndex };
                }
                await new Promise(resolve => setTimeout(resolve, RETRY_DELAY));
            }
        }
    }

    /**
     * Clear existing variations before saving new ones
     */
    async function clearExistingVariations() {
        if (!clearVariationsUrl) return { success: true };

        try {
            const response = await fetch(clearVariationsUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    product_id: currentProductId
                })
            });

            return await response.json();
        } catch (error) {
            return { success: false, message: error.message };
        }
    }

    /**
     * Main function to save all variations in batches
     */
    async function saveVariationsInBatches(productId, onComplete) {
        currentProductId = productId || currentProductId;

        if (!currentProductId) {
            console.error('Product ID is required for batch saving');
            if (onComplete) onComplete(false, 'Product ID is required');
            return false;
        }

        const variations = extractVariationsFromForm();

        if (variations.length === 0) {
            console.log('No variations to save');
            if (onComplete) onComplete(true, 'No variations to save');
            return true;
        }

        // Show progress modal
        progressModal.show();
        updateProgress(0, 1, 'Preparing to save variations...');

        // Clear existing variations first
        const clearResult = await clearExistingVariations();
        if (!clearResult.success) {
            progressModal.hide();
            toastMagic.error('Failed to clear existing variations: ' + (clearResult.message || 'Unknown error'));
            if (onComplete) onComplete(false, clearResult.message);
            return false;
        }

        const batches = splitIntoBatches(variations, BATCH_SIZE);
        const totalBatches = batches.length;
        let failedBatches = [];

        updateProgress(0, totalBatches, `Saving 0 of ${totalBatches} batches...`);

        for (let i = 0; i < batches.length; i++) {
            const isFirst = i === 0;
            const isLast = i === batches.length - 1;

            updateProgress(i + 1, totalBatches, `Saving batch ${i + 1} of ${totalBatches}...`);

            const result = await sendBatch(batches[i], i, totalBatches, isFirst, isLast);

            if (!result.success) {
                failedBatches.push({
                    batchIndex: i,
                    error: result.error,
                    variations: batches[i]
                });
            }

            // Small delay between batches to prevent server overload
            if (i < batches.length - 1) {
                await new Promise(resolve => setTimeout(resolve, 200));
            }
        }

        progressModal.hide();

        if (failedBatches.length > 0) {
            const failedCount = failedBatches.reduce((sum, b) => sum + b.variations.length, 0);
            toastMagic.warning(`${variations.length - failedCount} variations saved. ${failedCount} failed.`);
            if (onComplete) onComplete(false, 'Some batches failed', failedBatches);
            return false;
        }

        toastMagic.success(`All ${variations.length} variations saved successfully!`);
        if (onComplete) onComplete(true, 'All variations saved');
        return true;
    }

    /**
     * Check if batch saving is needed (more than threshold variants)
     */
    function shouldUseBatchSaving() {
        const variations = extractVariationsFromForm();
        return variations.length > BATCH_SIZE;
    }

    /**
     * Get variation count from form
     */
    function getVariationCount() {
        return document.querySelectorAll('input[name="type[]"]').length;
    }

    // Public API
    return {
        init: init,
        saveVariationsInBatches: saveVariationsInBatches,
        shouldUseBatchSaving: shouldUseBatchSaving,
        getVariationCount: getVariationCount,
        extractVariationsFromForm: extractVariationsFromForm,
        BATCH_SIZE: BATCH_SIZE
    };
})();

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = BatchVariationSaver;
}
