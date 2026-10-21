async function loadDeepSeek() {
  const card = document.getElementById('deepseek-card');
  const availability = document.getElementById('deepseek-card-availability');
  const balance = document.getElementById('deepseek-card-balance');

  const setState = (ok) => {
    card.classList.remove('aidash-card-green', 'aidash-card-red');
    card.classList.add(ok ? 'aidash-card-green' : 'aidash-card-red');
  };

  try {
    const response = await fetch('/api/deepseek-balance.php');
    if (!response.ok) {
        throw new Error('HTTP ' + response.status);
    }

    const data = await response.json();
    if (typeof data.is_available !== 'boolean' ||
        !Array.isArray(data.balance_infos) ||
        data.balance_infos.length === 0) {
        throw new Error('Unexpected response shape');
    }

    const info = data.balance_infos[0];
    availability.textContent = data.is_available ? 'Yes' : 'No';
    balance.textContent = info.total_balance + ' ' + info.currency;
    setState(true);
  } catch (err) {
    availability.textContent = 'Error';
    balance.textContent = '—';
    setState(false);
  }
}

document.addEventListener("DOMContentLoaded", (event) => { 
  loadDeepSeek();
});