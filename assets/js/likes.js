
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
        
        // Add loading state
        button.disabled = true;
        button.classList.add('animating');

        // Make AJAX request
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
                // Update button state
                button.setAttribute('data-liked', data.liked.toString());
                
                // Update visual state
                if (data.liked) {
                    button.classList.add('liked');
                } else {
                    button.classList.remove('liked');
                }
                
                // Update like count
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
            // Remove loading state
            button.disabled = false;
            
            // Remove animation class after CSS animation completes
            setTimeout(() => {
                button.classList.remove('animating');
            }, 600);
        });
    }
});
