/**
 * SISTEMA DE INVENTARIOS Y FACTURACIÓN
 * TECNOXPERT - JavaScript Principal
 */

// Configuración global
const APP_CONFIG = {
    baseUrl: window.location.origin + '/local/public/',
    apiUrl: window.location.origin + '/local/public/api/',
    csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
};

// Utilidades globales
const Utils = {
    // Formatear moneda
    formatCurrency: (amount, currency = 'COP') => {
        return new Intl.NumberFormat('es-CO', {
            style: 'currency',
            currency: currency,
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(amount);
    },

    // Formatear fecha
    formatDate: (date, format = 'DD/MM/YYYY') => {
        const d = new Date(date);
        const day = String(d.getDate()).padStart(2, '0');
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const year = d.getFullYear();
        
        return format
            .replace('DD', day)
            .replace('MM', month)
            .replace('YYYY', year);
    },

    // Formatear número
    formatNumber: (number, decimals = 0) => {
        return new Intl.NumberFormat('es-CO', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(number);
    },

    // Validar email
    isValidEmail: (email) => {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    },

    // Mostrar notificación
    showNotification: (message, type = 'info', duration = 5000) => {
        const alertClass = `alert-${type}`;
        const alertHtml = `
            <div class="alert ${alertClass} alert-dismissible fade show position-fixed" 
                 style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', alertHtml);
        
        setTimeout(() => {
            const alert = document.querySelector('.alert');
            if (alert) {
                alert.remove();
            }
        }, duration);
    },

    // Confirmar acción
    confirm: (message, callback) => {
        if (confirm(message)) {
            callback();
        }
    },

    // Cargar datos con AJAX
    ajax: (url, options = {}) => {
        const defaultOptions = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': APP_CONFIG.csrfToken
            }
        };

        const finalOptions = { ...defaultOptions, ...options };

        return fetch(url, finalOptions)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .catch(error => {
                console.error('Error en AJAX:', error);
                Utils.showNotification('Error en la comunicación con el servidor', 'danger');
                throw error;
            });
    }
};

// Clase para manejo de formularios
class FormHandler {
    constructor(formSelector, options = {}) {
        this.form = document.querySelector(formSelector);
        this.options = {
            validateOnSubmit: true,
            showSuccessMessage: true,
            redirectOnSuccess: false,
            redirectUrl: '',
            ...options
        };
        
        this.init();
    }

    init() {
        if (!this.form) return;
        
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));
        this.setupValidation();
    }

    setupValidation() {
        // Validación de campos requeridos
        const requiredFields = this.form.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            field.addEventListener('blur', () => this.validateField(field));
            field.addEventListener('input', () => this.clearFieldError(field));
        });

        // Validación de email
        const emailFields = this.form.querySelectorAll('input[type="email"]');
        emailFields.forEach(field => {
            field.addEventListener('blur', () => this.validateEmail(field));
        });

        // Validación de números
        const numberFields = this.form.querySelectorAll('input[type="number"]');
        numberFields.forEach(field => {
            field.addEventListener('input', () => this.validateNumber(field));
        });
    }

    validateField(field) {
        const value = field.value.trim();
        const isRequired = field.hasAttribute('required');
        
        if (isRequired && !value) {
            this.showFieldError(field, 'Este campo es requerido');
            return false;
        }
        
        this.clearFieldError(field);
        return true;
    }

    validateEmail(field) {
        const value = field.value.trim();
        if (value && !Utils.isValidEmail(value)) {
            this.showFieldError(field, 'Ingrese un email válido');
            return false;
        }
        return true;
    }

    validateNumber(field) {
        const value = field.value;
        if (value && isNaN(value)) {
            this.showFieldError(field, 'Ingrese un número válido');
            return false;
        }
        return true;
    }

    showFieldError(field, message) {
        this.clearFieldError(field);
        
        field.classList.add('is-invalid');
        const errorDiv = document.createElement('div');
        errorDiv.className = 'invalid-feedback';
        errorDiv.textContent = message;
        field.parentNode.appendChild(errorDiv);
    }

    clearFieldError(field) {
        field.classList.remove('is-invalid');
        const errorDiv = field.parentNode.querySelector('.invalid-feedback');
        if (errorDiv) {
            errorDiv.remove();
        }
    }

    validateForm() {
        let isValid = true;
        const fields = this.form.querySelectorAll('input, select, textarea');
        
        fields.forEach(field => {
            if (!this.validateField(field)) {
                isValid = false;
            }
        });
        
        return isValid;
    }

    async handleSubmit(e) {
        e.preventDefault();
        
        if (this.options.validateOnSubmit && !this.validateForm()) {
            Utils.showNotification('Por favor, corrija los errores en el formulario', 'warning');
            return;
        }

        const submitBtn = this.form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        // Mostrar loading
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Procesando...';

        try {
            const formData = new FormData(this.form);
            const response = await Utils.ajax(this.form.action, {
                method: this.form.method,
                body: formData
            });

            if (response.success) {
                if (this.options.showSuccessMessage) {
                    Utils.showNotification(response.message || 'Operación exitosa', 'success');
                }
                
                if (this.options.redirectOnSuccess) {
                    setTimeout(() => {
                        window.location.href = this.options.redirectUrl || response.redirect || '/';
                    }, 1000);
                }
            } else {
                Utils.showNotification(response.message || 'Error en la operación', 'danger');
            }
        } catch (error) {
            Utils.showNotification('Error en la comunicación con el servidor', 'danger');
        } finally {
            // Restaurar botón
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    }
}

// Clase para manejo de tablas
class TableHandler {
    constructor(tableSelector, options = {}) {
        this.table = document.querySelector(tableSelector);
        this.options = {
            searchable: true,
            sortable: true,
            pagination: true,
            itemsPerPage: 10,
            ...options
        };
        
        this.currentPage = 1;
        this.filteredData = [];
        this.originalData = [];
        
        this.init();
    }

    init() {
        if (!this.table) return;
        
        this.loadData();
        this.setupSearch();
        this.setupSorting();
        this.setupPagination();
    }

    loadData() {
        const rows = Array.from(this.table.querySelectorAll('tbody tr'));
        this.originalData = rows.map(row => ({
            element: row,
            data: this.extractRowData(row)
        }));
        this.filteredData = [...this.originalData];
    }

    extractRowData(row) {
        const cells = row.querySelectorAll('td');
        const data = {};
        
        cells.forEach((cell, index) => {
            data[`col${index}`] = cell.textContent.trim();
        });
        
        return data;
    }

    setupSearch() {
        if (!this.options.searchable) return;
        
        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.className = 'form-control mb-3';
        searchInput.placeholder = 'Buscar...';
        
        this.table.parentNode.insertBefore(searchInput, this.table);
        
        searchInput.addEventListener('input', (e) => {
            this.filterData(e.target.value);
        });
    }

    filterData(searchTerm) {
        if (!searchTerm) {
            this.filteredData = [...this.originalData];
        } else {
            this.filteredData = this.originalData.filter(item => {
                return Object.values(item.data).some(value => 
                    value.toLowerCase().includes(searchTerm.toLowerCase())
                );
            });
        }
        
        this.currentPage = 1;
        this.renderTable();
    }

    setupSorting() {
        if (!this.options.sortable) return;
        
        const headers = this.table.querySelectorAll('th[data-sort]');
        headers.forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => {
                this.sortByColumn(header.dataset.sort);
            });
        });
    }

    sortByColumn(columnIndex) {
        this.filteredData.sort((a, b) => {
            const aValue = a.data[`col${columnIndex}`];
            const bValue = b.data[`col${columnIndex}`];
            
            // Intentar ordenar como número
            const aNum = parseFloat(aValue);
            const bNum = parseFloat(bValue);
            
            if (!isNaN(aNum) && !isNaN(bNum)) {
                return aNum - bNum;
            }
            
            // Ordenar como texto
            return aValue.localeCompare(bValue);
        });
        
        this.renderTable();
    }

    setupPagination() {
        if (!this.options.pagination) return;
        
        const paginationContainer = document.createElement('div');
        paginationContainer.className = 'd-flex justify-content-between align-items-center mt-3';
        this.table.parentNode.appendChild(paginationContainer);
        
        this.paginationContainer = paginationContainer;
        this.renderPagination();
    }

    renderTable() {
        const tbody = this.table.querySelector('tbody');
        tbody.innerHTML = '';
        
        const startIndex = (this.currentPage - 1) * this.options.itemsPerPage;
        const endIndex = startIndex + this.options.itemsPerPage;
        const pageData = this.filteredData.slice(startIndex, endIndex);
        
        pageData.forEach(item => {
            tbody.appendChild(item.element.cloneNode(true));
        });
        
        if (this.options.pagination) {
            this.renderPagination();
        }
    }

    renderPagination() {
        if (!this.paginationContainer) return;
        
        const totalPages = Math.ceil(this.filteredData.length / this.options.itemsPerPage);
        const startItem = (this.currentPage - 1) * this.options.itemsPerPage + 1;
        const endItem = Math.min(this.currentPage * this.options.itemsPerPage, this.filteredData.length);
        
        this.paginationContainer.innerHTML = `
            <div class="text-muted">
                Mostrando ${startItem}-${endItem} de ${this.filteredData.length} registros
            </div>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item ${this.currentPage === 1 ? 'disabled' : ''}">
                        <a class="page-link" href="#" data-page="${this.currentPage - 1}">Anterior</a>
                    </li>
                    ${this.generatePageNumbers(totalPages)}
                    <li class="page-item ${this.currentPage === totalPages ? 'disabled' : ''}">
                        <a class="page-link" href="#" data-page="${this.currentPage + 1}">Siguiente</a>
                    </li>
                </ul>
            </nav>
        `;
        
        // Event listeners para paginación
        this.paginationContainer.querySelectorAll('.page-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const page = parseInt(e.target.dataset.page);
                if (page >= 1 && page <= totalPages) {
                    this.currentPage = page;
                    this.renderTable();
                }
            });
        });
    }

    generatePageNumbers(totalPages) {
        let html = '';
        const maxVisible = 5;
        let start = Math.max(1, this.currentPage - Math.floor(maxVisible / 2));
        let end = Math.min(totalPages, start + maxVisible - 1);
        
        if (end - start + 1 < maxVisible) {
            start = Math.max(1, end - maxVisible + 1);
        }
        
        for (let i = start; i <= end; i++) {
            html += `
                <li class="page-item ${i === this.currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
            `;
        }
        
        return html;
    }
}

// Clase para manejo de modales
class ModalHandler {
    constructor(modalSelector) {
        this.modal = document.querySelector(modalSelector);
        this.init();
    }

    init() {
        if (!this.modal) return;
        
        // Event listeners para cerrar modal
        this.modal.querySelectorAll('[data-bs-dismiss="modal"]').forEach(element => {
            element.addEventListener('click', () => this.hide());
        });
        
        // Cerrar al hacer clic fuera del modal
        this.modal.addEventListener('click', (e) => {
            if (e.target === this.modal) {
                this.hide();
            }
        });
    }

    show() {
        this.modal.classList.add('show');
        this.modal.style.display = 'block';
        document.body.classList.add('modal-open');
        
        // Focus en el primer input
        const firstInput = this.modal.querySelector('input, select, textarea');
        if (firstInput) {
            firstInput.focus();
        }
    }

    hide() {
        this.modal.classList.remove('show');
        this.modal.style.display = 'none';
        document.body.classList.remove('modal-open');
    }

    setContent(title, body) {
        const titleElement = this.modal.querySelector('.modal-title');
        const bodyElement = this.modal.querySelector('.modal-body');
        
        if (titleElement) titleElement.textContent = title;
        if (bodyElement) bodyElement.innerHTML = body;
    }
}

// Inicialización cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Inicializar popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Auto-hide alerts después de 5 segundos
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
        alerts.forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);

    // Confirmar eliminación
    document.querySelectorAll('[data-confirm]').forEach(element => {
        element.addEventListener('click', (e) => {
            const message = element.dataset.confirm || '¿Está seguro de realizar esta acción?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // Formatear campos de moneda
    document.querySelectorAll('.currency-input').forEach(input => {
        input.addEventListener('input', (e) => {
            let value = e.target.value.replace(/[^\d]/g, '');
            if (value) {
                value = parseInt(value);
                e.target.value = Utils.formatCurrency(value).replace(/[^\d]/g, '');
            }
        });
    });

    // Formatear campos de fecha
    document.querySelectorAll('.date-input').forEach(input => {
        input.addEventListener('input', (e) => {
            let value = e.target.value.replace(/[^\d]/g, '');
            if (value.length >= 8) {
                const day = value.substring(0, 2);
                const month = value.substring(2, 4);
                const year = value.substring(4, 8);
                e.target.value = `${day}/${month}/${year}`;
            }
        });
    });
});

// Exportar utilidades globales
window.Utils = Utils;
window.FormHandler = FormHandler;
window.TableHandler = TableHandler;
window.ModalHandler = ModalHandler;

