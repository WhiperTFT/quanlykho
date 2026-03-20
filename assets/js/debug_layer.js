// cleaned: console logs optimized, debug system applied
window.__DEV__ = (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1');

window.devLog = function(...args) {
    if (window.__DEV__) {
        console.log(...args);
    }
};

// Production Hardening Layer
if (!window.__DEV__) {
    console.log = function(){};
    console.info = function(){};
    console.debug = function(){};
    console.warn = function(){};
    // Note: console.error is INTENTIONALLY preserved.
}
