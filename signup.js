const form = document.getElementById('signupForm');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirmPassword');
    const message = document.getElementById('passwordMessage');

    form.addEventListener('submit', function(event) {
      if (password.value !== confirmPassword.value) {
        event.preventDefault(); 
        message.style.display = 'block';
      } else {
        message.style.display = 'none';
      }
    });

    
    confirmPassword.addEventListener('input', function() {
      if (password.value === confirmPassword.value) {
        message.style.display = 'none';
        confirmPassword.style.borderColor = 'green';
      } else {
        message.style.display = 'block';
        confirmPassword.style.borderColor = 'red';
      }
    });