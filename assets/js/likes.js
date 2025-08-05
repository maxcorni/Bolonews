
/**
 * Gère les interactions de like sur les articles.
 * 
 * - Ajoute un écouteur d'événement au chargement du DOM pour détecter les clics sur les boutons de like.
 * - Lorsqu'un bouton de like est cliqué, envoie une requête POST pour basculer l'état du like de l'article correspondant.
 * - Met à jour l'interface utilisateur en fonction de la réponse du serveur (état du like et compteur de likes).
 * - Ajoute une animation lors du clic et désactive temporairement le bouton pour éviter les clics multiples.
 * - Reactive le bouton après la réponse du serveur.
 */

document.addEventListener('DOMContentLoaded', function() {
    

    document.addEventListener('click', function(e) {
        if (e.target.closest('.like-button')) {
            handleLikeClick(e.target.closest('.like-button'));
        }
    });

    function handleLikeClick(button) {
        if (button.disabled) {
            return;
        }

        const articleId = button.getAttribute('data-article-id');
        button.disabled = true;
        button.classList.add('animating');

        fetch(`/article/toggle-like/${articleId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                button.setAttribute('data-liked', data.liked.toString());
                
                if (data.liked) {
                    button.classList.add('liked');
                } else {
                    button.classList.remove('liked');
                }
                const countElement = button.querySelector('.like-count');
                if (countElement) {
                    countElement.textContent = data.likeCount;
                }
            } else {
                console.error('Erreur:', data.error || 'Une erreur est survenue');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
        })
        .finally(() => {
            button.disabled = false;
            
            setTimeout(() => {
                button.classList.remove('animating');
            }, 600);
        });
    }
});
