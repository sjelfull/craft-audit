/**
 * Audit plugin for Craft CMS
 *
 * Index Field JS
 *
 * @author    Superbig
 * @copyright Copyright (c) 2017 Superbig
 * @link      https://superbig.co
 * @package   Audit
 * @since     1.0.0
 */

(function() {
    // Handle clear filters button
    const clearButton = document.getElementById('clear-filters');
    if (clearButton) {
        clearButton.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Clear the form inputs if they exist
            const startDateField = document.getElementById('startDate');
            const endDateField = document.getElementById('endDate');
            
            if (startDateField) {
                startDateField.value = '';
            }
            if (endDateField) {
                endDateField.value = '';
            }
            
            // Navigate to the base URL without query parameters
            window.location.href = window.location.pathname;
        });
    }
})();

