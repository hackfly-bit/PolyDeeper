import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Dark mode initialization
document.addEventListener('alpine:init', () => {
    Alpine.store('darkMode', {
        on: false,
        
        init() {
            // Check local storage or system preference
            if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                this.on = true;
            }
            this.applyTheme();
        },
        
        toggle() {
            this.on = !this.on;
            localStorage.setItem('theme', this.on ? 'dark' : 'light');
            this.applyTheme();
        },
        
        applyTheme() {
            if (this.on) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        }
    });
});

Alpine.start();