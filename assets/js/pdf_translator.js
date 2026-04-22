/**
 * Utility for translating PDF content on the fly before capturing with html2canvas.
 * File: assets/js/pdf_translator.js
 */

const PDFTranslator = (() => {
    const cache = {};
    const originals = new Map();

    /**
     * Fetches language JSON from the server.
     * @param {string} lang - 'en' or 'vi'
     * @returns {Promise<Object>}
     */
    async function fetchTranslations(lang) {
        if (cache[lang]) return cache[lang];
        try {
            const resp = await fetch(`process/get_lang_json.php?lang=${lang}`);
            const data = await resp.json();
            cache[lang] = data;
            return data;
        } catch (e) {
            console.error('Failed to fetch translations for', lang, e);
            return null;
        }
    }

    /**
     * Replaces text of elements with data-lang-key attribute.
     * @param {string} containerSelector - e.g. '#pdf-export-content'
     * @param {string} lang - 'en' or 'vi'
     */
    async function translate(containerSelector, lang) {
        const dict = await fetchTranslations(lang);
        if (!dict) return;

        const container = document.querySelector(containerSelector);
        if (!container) return;

        const elements = container.querySelectorAll('[data-lang-key]');
        elements.forEach(el => {
            const key = el.getAttribute('data-lang-key');
            if (dict[key]) {
                // Save original if not already saved
                if (!originals.has(el)) {
                    originals.set(el, el.innerText);
                }
                el.innerText = dict[key];
            }
        });
    }

    /**
     * Restores original text for all previously translated elements.
     */
    function restore() {
        originals.forEach((text, el) => {
            el.innerText = text;
        });
        originals.clear();
    }

    return {
        translate,
        restore
    };
})();
