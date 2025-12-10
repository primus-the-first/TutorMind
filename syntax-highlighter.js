/**
 * Code Syntax Highlighting Loader
 * Lazily loads highlight.js only when code blocks are detected on the page
 * This prevents the library from blocking initial page load
 */

class SyntaxHighlighter {
    constructor() {
        this.loaded = false;
        this.loading = false;
        this.queue = [];
    }

    // Load highlight.js from CDN asynchronously
    async load() {
        if (this.loaded) return true;
        if (this.loading) {
            // Wait for current load to complete
            return new Promise((resolve) => {
                this.queue.push(resolve);
            });
        }

        this.loading = true;

        try {
            // Load CSS
            const css = document.createElement('link');
            css.rel = 'stylesheet';
            css.href = 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css';
            document.head.appendChild(css);

            // Load JS (only core + common languages for smaller size)
            await new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js';
                script.async = true;
                script.onload = resolve;
                script.onerror = reject;
                document.head.appendChild(script);
            });

            this.loaded = true;
            this.loading = false;

            // Resolve all queued promises
            this.queue.forEach(resolve => resolve(true));
            this.queue = [];

            console.log('Syntax highlighter loaded');
            return true;
        } catch (error) {
            console.error('Failed to load syntax highlighter:', error);
            this.loading = false;
            this.queue.forEach(resolve => resolve(false));
            this.queue = [];
            return false;
        }
    }

    // Highlight a specific element or container
    async highlight(container) {
        const codeBlocks = container.querySelectorAll('pre code');
        
        if (codeBlocks.length === 0) {
            return; // No code blocks, don't load library
        }

        // Load library if not already loaded
        const success = await this.load();
        
        if (success && window.hljs) {
            codeBlocks.forEach(block => {
                hljs.highlightElement(block);
            });
        }
    }

    // Auto-detect and highlight all code blocks on page
    async highlightAll() {
        await this.highlight(document.body);
    }
}

// Create global instance
window.syntaxHighlighter = new SyntaxHighlighter();

// Support both module and global usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SyntaxHighlighter;
}
