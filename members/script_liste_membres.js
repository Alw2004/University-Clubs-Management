document.addEventListener('DOMContentLoaded', function() {
    // Éléments DOM
    const roleFilter = document.getElementById('roleFilter');
    const membersList = document.querySelector('.members-list');
    const memberCards = document.querySelectorAll('.member-card');
    const statTotal = document.querySelector('.stat-card:nth-child(1) .stat-value');
    const statActive = document.querySelector('.stat-card:nth-child(2) .stat-value');
    const statExecutives = document.querySelector('.stat-card:nth-child(3) .stat-value');

    // Fonction de filtrage par rôle
    function filterMembers() {
        const selectedRole = roleFilter.value.toLowerCase();
        let visibleCount = 0;
        let activeCount = 0;
        let executiveCount = 0;

        memberCards.forEach(card => {
            const cardRole = card.dataset.role;
            const isVisible = !selectedRole || cardRole === selectedRole;
            
            card.style.display = isVisible ? 'flex' : 'none';
            
            if (isVisible) {
                visibleCount++;
                if (cardRole === 'member') {
                    activeCount++;
                } else {
                    executiveCount++;
                }
            }
        });

        // Mise à jour des statistiques en temps réel
        if (roleFilter.value === '') {
            // Réinitialiser aux valeurs totales si aucun filtre
            statTotal.textContent = memberCards.length;
            statActive.textContent = document.querySelectorAll('.member-card[data-role="member"]').length;
            statExecutives.textContent = memberCards.length - statActive.textContent;
        } else {
            // Afficher les comptes filtrés
            statTotal.textContent = visibleCount;
            statActive.textContent = activeCount;
            statExecutives.textContent = executiveCount;
        }

        // Afficher un message si aucun résultat
        const noResults = document.querySelector('.no-results') || document.createElement('div');
        if (visibleCount === 0) {
            noResults.className = 'no-results';
            noResults.textContent = 'Aucun membre trouvé avec ce rôle';
            membersList.appendChild(noResults);
        } else if (document.querySelector('.no-results')) {
            membersList.removeChild(document.querySelector('.no-results'));
        }
    }

    // Écouteur d'événement pour le filtre
    roleFilter.addEventListener('change', filterMembers);

    // Fonction de recherche (si vous ajoutez une barre de recherche)
    function setupSearch() {
        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.placeholder = 'Rechercher un membre...';
        searchInput.className = 'search-box';
        searchInput.id = 'searchInput';
        
        const searchContainer = document.querySelector('.search-container');
        searchContainer.prepend(searchInput);

        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            
            memberCards.forEach(card => {
                const name = card.querySelector('.member-name').textContent.toLowerCase();
                const email = card.querySelector('.member-details span').textContent.toLowerCase();
                const isMatch = name.includes(searchTerm) || email.includes(searchTerm);
                
                if (card.style.display !== 'none') {
                    card.style.display = isMatch ? 'flex' : 'none';
                }
            });
        });
    }

    // Initialisation
    filterMembers(); // Filtre initial
    setupSearch(); // Activer la recherche si besoin

    // Animation au chargement
    gsap.from('.member-card', {
        duration: 0.5,
        opacity: 0,
        y: 20,
        stagger: 0.1,
        ease: "power2.out"
    });
});