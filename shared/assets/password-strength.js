(() => {
  const scorePassword = (value) => {
    if (!value) return 0;
    let score = 0;
    if (value.length >= 12) score += 1;
    if (value.length >= 16) score += 1;
    if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score += 1;
    if (/\d/.test(value)) score += 1;
    if (/[^A-Za-z0-9]/.test(value)) score += 1;
    return Math.min(score, 4);
  };

  const labels = ['Too weak', 'Weak', 'Fair', 'Strong', 'Very strong'];
  const colors = ['bg-danger', 'bg-danger', 'bg-warning', 'bg-primary', 'bg-success'];

  document.querySelectorAll('.js-password-strength-input').forEach((input) => {
    const container = input.closest('.mb-3, .col-12, .col-md-6, .col-md-12, .col') || input.parentElement;
    if (!container) return;
    const bar = container.querySelector('.js-password-strength-bar');
    const text = container.querySelector('.js-password-strength-text');
    if (!bar || !text) return;

    const update = () => {
      const score = scorePassword(input.value);
      const percentage = Math.max(8, score * 25);
      bar.style.width = `${percentage}%`;
      bar.classList.remove('bg-danger', 'bg-warning', 'bg-primary', 'bg-success');
      bar.classList.add(colors[score]);
      text.textContent = `Strength: ${labels[score]}`;
    };

    input.addEventListener('input', update);
    update();
  });
})();
