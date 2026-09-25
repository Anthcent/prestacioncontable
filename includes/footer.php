        </div> <!-- Cierra p-8 -->
        
        <footer class="bg-white border-t border-slate-200 py-6 mt-auto text-center text-sm text-slate-700 no-print">
            <p>&copy; <?php echo date('Y'); ?> <strong class="text-slate-900">PRIME CONTADORES PÚBLICOS</strong> - Todos los derechos reservados.</p>
            <p class="text-xs text-slate-600 font-medium mt-1">Sistema Integral de Nómina y Prestaciones Sociales</p>
        </footer>
    </main> <!-- Cierra flex-1 -->
    
    <!-- Scripts globales -->
    <script>
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
