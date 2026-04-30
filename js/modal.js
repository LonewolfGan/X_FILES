/**
 * XFILES Modal System
 * Système modal réutilisable pour remplacer alert(), confirm(), et notifications
 * Couleurs strictes: Jaune #fbbf24 et Noir #000000 uniquement
 */

(function() {
    'use strict';

    // ==========================================
    // CONFIGURATION
    // ==========================================
    const CONFIG = {
        animationDuration: 300,
        autoCloseDelay: 5000,
        zIndex: 9999
    };

    // ==========================================
    // UTILITAIRES
    // ==========================================
    function createElement(tag, className, html = '') {
        const el = document.createElement(tag);
        if (className) el.className = className;
        if (html) el.innerHTML = html;
        return el;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ==========================================
    // MODAL DE CONFIRMATION (remplace confirm())
    // ==========================================
    function createConfirmModal() {
        const overlay = createElement('div', 'xmodal-confirm-overlay');
        overlay.id = 'xmodal-confirm';
        overlay.innerHTML = `
            <div class="xmodal-confirm-container">
                <div class="xmodal-confirm-header">
                    <h3 class="xmodal-confirm-title">Confirmer l'action</h3>
                    <button class="xmodal-confirm-close" onclick="XModal.closeConfirm()" aria-label="Fermer">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="xmodal-confirm-body">
                    <p class="xmodal-confirm-message">Êtes-vous sûr ?</p>
                </div>
                <div class="xmodal-confirm-actions">
                    <button type="button" class="xmodal-btn xmodal-btn-secondary" onclick="XModal.closeConfirm()">
                        Annuler
                    </button>
                    <button type="button" class="xmodal-btn xmodal-btn-primary" id="xmodal-confirm-btn">
                        Confirmer
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
        return overlay;
    }

    // ==========================================
    // MODAL D'ALERTE (remplace alert())
    // ==========================================
    function createAlertModal() {
        const overlay = createElement('div', 'xmodal-alert-overlay');
        overlay.id = 'xmodal-alert';
        overlay.innerHTML = `
            <div class="xmodal-alert-container">
                <div class="xmodal-alert-icon">
                    <i class="fa-solid fa-circle-info"></i>
                </div>
                <div class="xmodal-alert-body">
                    <h3 class="xmodal-alert-title">Information</h3>
                    <p class="xmodal-alert-message"></p>
                </div>
                <div class="xmodal-alert-actions">
                    <button type="button" class="xmodal-btn xmodal-btn-primary" onclick="XModal.closeAlert()">
                        OK
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
        return overlay;
    }

    // ==========================================
    // SYSTÈME DE TOASTS/NOTIFICATIONS
    // ==========================================
    function createToastContainer() {
        const container = createElement('div', 'xmodal-toast-container');
        container.id = 'xmodal-toast-container';
        document.body.appendChild(container);
        return container;
    }

    function showToast(message, type = 'info', duration = CONFIG.autoCloseDelay) {
        let container = document.getElementById('xmodal-toast-container');
        if (!container) {
            container = createToastContainer();
        }

        const icons = {
            success: 'fa-circle-check',
            error: 'fa-circle-xmark',
            warning: 'fa-triangle-exclamation',
            info: 'fa-circle-info'
        };

        const toast = createElement('div', `xmodal-toast xmodal-toast-${type}`);
        toast.innerHTML = `
            <div class="xmodal-toast-icon">
                <i class="fa-solid ${icons[type] || icons.info}"></i>
            </div>
            <div class="xmodal-toast-message">${escapeHtml(message)}</div>
            <button class="xmodal-toast-close" onclick="this.parentElement.remove()" aria-label="Fermer">
                <i class="fa-solid fa-xmark"></i>
            </button>
        `;

        container.appendChild(toast);

        // Animation d'entrée
        requestAnimationFrame(() => {
            toast.classList.add('show');
        });

        // Auto-fermeture
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), CONFIG.animationDuration);
        }, duration);
    }

    // ==========================================
    // API PUBLIQUE
    // ==========================================
    window.XModal = {
        // Initialisation
        init: function() {
            if (!document.getElementById('xmodal-confirm')) {
                createConfirmModal();
            }
            if (!document.getElementById('xmodal-alert')) {
                createAlertModal();
            }
            if (!document.getElementById('xmodal-toast-container')) {
                createToastContainer();
            }
        },

        // Confirmation (remplace confirm())
        confirm: function(message, onConfirm, onCancel, title = 'Confirmer l\'action') {
            this.init();
            const overlay = document.getElementById('xmodal-confirm');
            const titleEl = overlay.querySelector('.xmodal-confirm-title');
            const messageEl = overlay.querySelector('.xmodal-confirm-message');
            const confirmBtn = document.getElementById('xmodal-confirm-btn');

            titleEl.textContent = title;
            messageEl.textContent = message;

            // Réinitialiser les handlers
            const newBtn = confirmBtn.cloneNode(true);
            confirmBtn.parentNode.replaceChild(newBtn, confirmBtn);

            newBtn.onclick = () => {
                this.closeConfirm();
                if (typeof onConfirm === 'function') onConfirm();
            };

            // Gestion de l'annulation
            overlay.oncancel = onCancel;

            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        },

        closeConfirm: function() {
            const overlay = document.getElementById('xmodal-confirm');
            if (overlay) {
                overlay.classList.remove('active');
                document.body.style.overflow = '';
                if (overlay.oncancel && typeof overlay.oncancel === 'function') {
                    overlay.oncancel();
                    overlay.oncancel = null;
                }
            }
        },

        // Alerte (remplace alert())
        alert: function(message, title = 'Information', onClose) {
            this.init();
            const overlay = document.getElementById('xmodal-alert');
            const titleEl = overlay.querySelector('.xmodal-alert-title');
            const messageEl = overlay.querySelector('.xmodal-alert-message');

            titleEl.textContent = title;
            messageEl.textContent = message;
            overlay.onclose = onClose;

            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';

            // Fermeture avec Escape
            const escapeHandler = (e) => {
                if (e.key === 'Escape') {
                    this.closeAlert();
                    document.removeEventListener('keydown', escapeHandler);
                }
            };
            document.addEventListener('keydown', escapeHandler);
        },

        closeAlert: function() {
            const overlay = document.getElementById('xmodal-alert');
            if (overlay) {
                overlay.classList.remove('active');
                document.body.style.overflow = '';
                if (overlay.onclose && typeof overlay.onclose === 'function') {
                    overlay.onclose();
                    overlay.onclose = null;
                }
            }
        },

        // Notifications/Toasts
        success: function(message, duration) {
            showToast(message, 'success', duration);
        },
        error: function(message, duration) {
            showToast(message, 'error', duration);
        },
        warning: function(message, duration) {
            showToast(message, 'warning', duration);
        },
        info: function(message, duration) {
            showToast(message, 'info', duration);
        },

        // Fonction utilitaire pour remplacer les confirm() inline dans les forms
        confirmForm: function(formElement, message, title) {
            if (typeof message === 'string') {
                this.confirm(message, () => {
                    formElement.submit();
                }, null, title || 'Confirmer');
            }
            return false;
        }
    };

    // Fermeture au clic sur l'overlay
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('xmodal-confirm-overlay') && e.target.classList.contains('active')) {
            XModal.closeConfirm();
        }
        if (e.target.classList.contains('xmodal-alert-overlay') && e.target.classList.contains('active')) {
            XModal.closeAlert();
        }
    });

    // Handler pour les formulaires avec data-confirm
    // Remplace: onsubmit="return confirm('message')"
    // Par: data-confirm="message"
    document.addEventListener('submit', function(e) {
        const form = e.target;
        const confirmMessage = form.getAttribute('data-confirm');

        if (confirmMessage && !form._xmodalConfirmed) {
            e.preventDefault();
            XModal.confirm(confirmMessage, function() {
                form._xmodalConfirmed = true;
                form.submit();
            }, null, 'Confirmer l\'action');
        }
    });

    // Auto-initialisation au chargement
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => XModal.init());
    } else {
        XModal.init();
    }
})();
