        </div> <!-- Cierra p-8 -->
        
        <?php if (basename($_SERVER['PHP_SELF']) !== 'index.php') include __DIR__ . '/quick_dock.php'; ?>

        <footer class="bg-white border-t border-slate-200 px-4 py-5 sm:py-6 mt-auto text-center text-xs sm:text-sm text-slate-700 no-print">
            <p>&copy; <?php echo date('Y'); ?> <strong class="text-slate-900">PRIME CONTADORES PÚBLICOS</strong> - Todos los derechos reservados.</p>
            <p class="text-xs text-slate-600 font-medium mt-1">Sistema de Cálculo de Prestaciones Sociales</p>
        </footer>
    </main> <!-- Cierra flex-1 -->
    
    <!-- Scripts globales -->
    <script>
        const toggleMobileNav = (open) => {
            document.body.classList.toggle('nav-open', open);
            document.getElementById('appSidebar')?.setAttribute('aria-hidden', open ? 'false' : 'true');
        };
        const toggleDesktopSidebar = (forceState) => {
            const shouldCollapse = typeof forceState === 'boolean'
                ? forceState
                : !document.body.classList.contains('sidebar-collapsed');
            document.body.classList.toggle('sidebar-collapsed', shouldCollapse);
            try { localStorage.setItem('primeSidebarCollapsed', shouldCollapse ? '1' : '0'); } catch (e) {}
            const button = document.getElementById('desktopSidebarToggle');
            if (button) button.setAttribute('aria-label', shouldCollapse ? 'Desplegar barra lateral' : 'Plegar barra lateral');
        };
        if (window.innerWidth >= 1024) {
            try { toggleDesktopSidebar(localStorage.getItem('primeSidebarCollapsed') === '1'); } catch (e) {}
        }

        window.toggleQuickDock = (forceState) => {
            const panel = document.getElementById('quickDockPanel');
            const backdrop = document.getElementById('quickDockBackdrop');
            const arrow = document.getElementById('quickDockArrow');
            if (!panel) return;
            const shouldOpen = typeof forceState === 'boolean' ? forceState : panel.classList.contains('translate-x-full');
            panel.classList.toggle('translate-x-full', !shouldOpen);
            if (backdrop) {
                backdrop.classList.toggle('hidden', !shouldOpen);
                requestAnimationFrame(() => backdrop.classList.toggle('opacity-0', !shouldOpen));
            }
            if (arrow) {
                arrow.classList.toggle('fa-chevron-left', !shouldOpen);
                arrow.classList.toggle('fa-chevron-right', shouldOpen);
            }
        };
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                toggleMobileNav(false);
                toggleQuickDock(false);
            }
        });
        document.querySelectorAll('#appSidebar a').forEach(link => link.addEventListener('click', () => toggleMobileNav(false)));

        // Utilidades JS globales
        const formatMoney = (amount) => {
            return new Intl.NumberFormat('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount);
        };
        
        const parseMoney = (str) => {
            if(!str) return 0;
            if(typeof str === 'number') return str;
            // Para formato VE: '.' es miles, ',' es decimales.
            // Replace '.' con nada, luego ',' con '.'
            return parseFloat(str.toString().replace(/\./g, '').replace(',', '.')) || 0;
        };
    </script>
</body>
</html>
