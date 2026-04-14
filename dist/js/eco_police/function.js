document.addEventListener("DOMContentLoaded", function () {
  // -------- ID Type & Upload Show/Hide --------
  const idSelect = document.getElementById("id_type_select");
  const uploadBox = document.getElementById("id_upload_wrapper");
  const otherWrapper = document.getElementById('other_id_wrapper');
  const otherInput = document.getElementById('other_id');

  idSelect.addEventListener("change", function () {
    // Show/hide ID upload
    if (this.value !== "") {
      uploadBox.classList.remove("d-none");
    } else {
      uploadBox.classList.add("d-none");
    }

    // Show/hide Other ID input
    if (this.value === 'Other') {
      otherWrapper.style.display = 'block';
      otherInput.required = true;
    } else {
      otherWrapper.style.display = 'none';
      otherInput.required = false;
      otherInput.value = '';
    }
  });

  // -------- ID Number Numeric Only --------
  const idNumberInput = document.getElementById('id_number');
  idNumberInput.addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '');
  });

  // -------- Contact Number Auto +639 --------
  const contactInput = document.getElementById('contact_number');
  contactInput.addEventListener('focus', function() {
    if (!this.value.startsWith('+639')) {
      this.value = '+639';
    }
  });

  contactInput.addEventListener('input', function() {
    let value = this.value;

    if (!value.startsWith('+639')) {
      value = '+639' + value.replace(/\D/g,'').slice(0,9);
    } else {
      let rest = value.slice(4).replace(/\D/g,'');
      if (rest.length > 9) rest = rest.slice(0,9);
      value = '+639' + rest;
    }
    this.value = value;
  });

  // -------- Real-time ID Number Duplicate Check --------
  const feedback = document.getElementById('id_feedback');
  const submitBtn = document.querySelector('button[type="submit"]');

  idNumberInput.addEventListener('input', function() {
    const idVal = this.value.trim();
    
    if(idVal === '') {
      feedback.textContent = '';
      submitBtn.disabled = false;
      return;
    }

    const base = window.BASE_URL || '';
    fetch(base + '/modules/eco_police/check_id_function.php?id_number=' + encodeURIComponent(idVal))
      .then(res => res.json())
      .then(data => {
        if(data.exists) {
          feedback.textContent = 'This ID Number is already registered!';
          submitBtn.disabled = true;
        } else {
          feedback.textContent = '';
          submitBtn.disabled = false;
        }
      })
      .catch(err => {
        console.error(err);
      });
  });

});
