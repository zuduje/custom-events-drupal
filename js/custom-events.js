(function (Drupal, once) {
  'use strict';

  const toast = {
    el: null,
    timer: null,
    init() {
      this.el = document.getElementById('me-toast');
    },
    show(message, type = 'success', duration = 4000) {
      if (!this.el) return;
      clearTimeout(this.timer);
      this.el.textContent = message;
      this.el.className = `me-toast me-toast-${type} me-toast-visible`;
      this.timer = setTimeout(() => {
        this.el.classList.remove('me-toast-visible');
      }, duration);
    },
  };

  async function getCsrfToken() {
    try {
      const res = await fetch('/session/token', { credentials: 'same-origin' });
      return await res.text();
    } catch {
      return '';
    }
  }

  function updateCounter(eventId, newCount) {
    const countEl = document.getElementById(`count-${eventId}`);
    if (!countEl) return;
    const numberEl = countEl.querySelector('.me-count-number');
    const labelEl  = countEl.querySelector('span:last-child');
    if (numberEl) {
      numberEl.style.transform  = 'scale(1.4)';
      numberEl.style.color      = 'var(--me-success)';
      numberEl.style.transition = 'all .3s';
      numberEl.textContent      = newCount;
      setTimeout(() => {
        numberEl.style.transform = 'scale(1)';
        numberEl.style.color     = '';
      }, 400);
    }
    if (labelEl) {
      labelEl.textContent = newCount === 1 ? 'inscrito' : 'inscritos';
    }
  }

  async function handleRegister(btn) {
    const eventId    = btn.dataset.eventId;
    const eventTitle = btn.dataset.eventTitle;
    if (!eventId) return;

    btn.classList.add('me-loading');
    btn.disabled = true;
    const originalText = btn.textContent;
    btn.textContent = '';

    try {
      const csrfToken = await getCsrfToken();

      const res = await fetch(`/api/eventos/${eventId}/registrar`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type':  'application/json',
          'X-CSRF-Token':  csrfToken,
          'Accept':        'application/json',
        },
      });

      const data = await res.json();

      if (data.success) {
        btn.textContent = '✓ ¡Inscrito!';
        btn.classList.remove('me-btn-primary', 'me-loading');
        btn.classList.add('me-btn-registered');
        btn.removeAttribute('data-event-id');
        btn.disabled = true;

        const card = btn.closest('.me-event-card');
        if (card) {
          card.classList.add('me-card-registered');
          const badges   = card.querySelector('.me-card-badges');
          const upcoming = badges ? badges.querySelector('.me-badge-upcoming') : null;
          if (upcoming) {
            const regBadge = document.createElement('span');
            regBadge.className   = 'me-badge me-badge-registered';
            regBadge.textContent = '✓ Inscrito';
            badges.prepend(regBadge);
            upcoming.remove();
          }
        }

        updateCounter(parseInt(eventId), parseInt(data.new_count));
        toast.show(`¡Listo! Estás inscrito en "${eventTitle}"`, 'success');

      } else {
        btn.textContent = originalText;
        btn.disabled    = false;
        btn.classList.remove('me-loading');
        toast.show(data.message || 'No se pudo completar la inscripción.', 'error');
      }

    } catch (err) {
      console.error('[custom_events] Error:', err);
      btn.textContent = originalText;
      btn.disabled    = false;
      btn.classList.remove('me-loading');
      toast.show('Error de conexión. Intenta nuevamente.', 'error');
    }
  }

  Drupal.behaviors.customEvents = {
    attach(context) {
      toast.init();
      once('me-register', '.me-register-btn', context).forEach((btn) => {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          handleRegister(this);
        });
      });
    },
  };

})(Drupal, once);
