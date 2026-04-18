<?php // inc/footer.php ?>
    </div> <script>
    document.addEventListener('DOMContentLoaded', function() {
        const parents = document.querySelectorAll('.nav-parent');
        
        parents.forEach(p => {
            p.addEventListener('click', function() {
                const sub = this.nextElementSibling;
                const isOpen = sub.style.display === 'block';
                
                // Nur schließen, wenn wir auf einen ANDEREN Hauptpunkt klicken
                // (Optional: Wenn du willst, dass immer nur EIN Baum offen ist)
                document.querySelectorAll('.submenu-container').forEach(el => {
                    if (el !== sub) {
                        el.style.display = 'none';
                        el.previousElementSibling.classList.remove('open');
                    }
                });
                
                // Den geklickten umschalten
                if (isOpen) {
                    sub.style.display = 'none';
                    this.classList.remove('open');
                } else {
                    sub.style.display = 'block';
                    this.classList.add('open');
                }
            });
        });
    });
    </script>
</body>
</html>