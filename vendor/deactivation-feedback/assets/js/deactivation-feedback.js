(function () {
    'use strict';

    window.DeactivationFeedback = window.DeactivationFeedback || {
        /**
         * @param {Object} config
         * @param {string} config.pluginFile e.g. "optionbay-product-options-addons-woo/optionbay.php"
         * @param {string} config.modalId
         * @param {string} config.ajaxUrl
         * @param {string} config.action
         * @param {string} config.nonce
         */
        init: function (config) {
            var row = document.querySelector('tr[data-plugin="' + config.pluginFile + '"]');
            if (!row) {
                return;
            }

            var deactivateLink = row.querySelector('.deactivate a');
            if (!deactivateLink) {
                return;
            }

            var modal = document.getElementById(config.modalId);
            if (!modal) {
                return;
            }

            var originalHref = deactivateLink.getAttribute('href');
            var form = modal.querySelector('.df-modal-form');
            var skipBtn = modal.querySelector('.df-skip');
            var submitBtn = modal.querySelector('.df-submit');
            var closeBtn = modal.querySelector('.df-close');

            function proceedToDeactivate() {
                window.location.href = originalHref;
            }

            function openModal() {
                modal.style.display = 'flex';
            }

            function closeModal() {
                modal.style.display = 'none';
            }

            deactivateLink.addEventListener('click', function (e) {
                e.preventDefault();
                openModal();
            });

            if (closeBtn) {
                closeBtn.addEventListener('click', function () {
                    closeModal();
                });
            }

            skipBtn.addEventListener('click', function () {
                proceedToDeactivate();
            });

            // Clicking the dark overlay closes the modal.
            modal.addEventListener('click', function (e) {
                if (e.target === modal) {
                    closeModal();
                }
            });

            // Show/hide the free-text field tied to the selected reason.
            var radios = form.querySelectorAll('input[name="df_reason"]');
            radios.forEach(function (radio) {
                radio.addEventListener('change', function () {
                    form.querySelectorAll('.df-reason-input').forEach(function (input) {
                        input.style.display = 'none';
                    });
                    if (radio.hasAttribute('data-has-input')) {
                        var input = form.querySelector('.df-reason-input[data-for="' + radio.value + '"]');
                        if (input) {
                            input.style.display = 'block';
                        }
                    }
                });
            });

            form.addEventListener('submit', function (e) {
                e.preventDefault();

                var selected = form.querySelector('input[name="df_reason"]:checked');

                if (!selected) {
                    proceedToDeactivate();
                    return;
                }

                var detailsInput = form.querySelector('.df-reason-input[data-for="' + selected.value + '"]');
                var details = detailsInput ? detailsInput.value : '';

                submitBtn.disabled = true;
                skipBtn.disabled = true;
                if (closeBtn) closeBtn.disabled = true;

                var body = new URLSearchParams();
                body.append('action', config.action);
                body.append('nonce', config.nonce);
                body.append('reason', selected.value);
                body.append('details', details);

                fetch(config.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString()
                }).then(function () {
                    proceedToDeactivate();
                }).catch(function () {
                    // Never block deactivation on a network failure.
                    proceedToDeactivate();
                });
            });
        }
    };
})();
