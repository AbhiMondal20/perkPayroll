
<footer class="main-footer d-flex justify-content-between align-items-center">
    <div>
        Powered by &copy;
        <a href="https://abhitechbot.in" target="_blank">AbhiTechBot</a>
        <script>document.write(new Date().getFullYear())</script>
        <a href="https://abhitechbot.in" target="_blank">Rhythm E-Clinic Solutions 4.2</a>
    </div>
    <div>
        <a href="privacy-policy" class="text-decoration-none">Privacy Policy</a>
    </div>
</footer>

<!-- Net check -->
<script src="../opd/admin/js/netCheck.js"></script>
<!-- Vendors -->
<script src="../opd/admin/js/vendors.min.js"></script>
<script src="../opd/assets/vendor_components/datatable/datatables.min.js"></script>
<!--Echarts-->
<script src="https://cdnjs.cloudflare.com/ajax/libs/echarts/6.0.0/echarts.min.js" integrity="sha512-4/g9GAdOdTpUP2mKClpKsEzaK7FQNgMjq+No0rX8XZlfrCGtbi4r+T/p5fnacsEC3zIAmHKLJUL7sh3/yVA4OQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<!--InputMask Js-->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.inputmask/5.0.9/jquery.inputmask.min.js" integrity="sha512-F5Ul1uuyFlGnIT1dk2c4kB4DBdi5wnBJjVhL7gQlGh46Xn0VhvD8kgxLtjdZ5YN83gybk/aASUAlpdoWUjRR3g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<!-- External libraries (before scripts that use them) -->
<!--<script src="https://unpkg.com/feather-icons"></script>-->
<!--<script src="js/apexcharts.js"></script>-->
<!-- Your app scripts -->

<script src="../opd/admin/js/template.js"></script>
<script src="../opd/admin/js/pages/dashboard4.js"></script>
<script src="../opd/admin/js/pages/validation.js"></script>
<script src="../opd/admin/js/pages/form-validation.js"></script>
<script src="../opd/admin/js/pages/advanced-form-element.js"></script>
<script src="../opd/admin/js/pages/dashboard.js"></script>
<script src="../opd/admin/js/pages/data-table.js"></script>
<script src="../opd/admin/js/pages/invoice.js"></script>
<script src="../opd/admin/assets/vendor_components/JqueryPrintArea/jquery.PrintArea.js"></script>

<script>
$(document).ready(function () {
  if ($.fn.DataTable) {
    // ✅ only init if not already initialised
    if (!$.fn.DataTable.isDataTable('#example')) {
      $('#example').DataTable({
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        responsive: true
      });
    }
  }
});
</script>

<script>
flatpickr("#from", {
    maxDate: "today",
    allowInput: false
});

flatpickr("#to", {
    maxDate: "today",
    allowInput: false
});
</script>

<style>
    #themeIcon.fa-moon {
        color: white;
    }

    #themeIcon.fa-sun {
        color: gold;
    }
</style>

<script>
    function goBack() {
        window.history.back();
    }
</script>

<script>
    const toggleBtn = document.getElementById('themeToggle');
    const body = document.getElementById('body');
    const themeIcon = document.getElementById('themeIcon');

    // Load saved theme
    if (localStorage.getItem('theme') === 'dark') {
        body.classList.add('dark-mode');
        themeIcon.classList.remove('fa-moon');
        themeIcon.classList.add('fa-sun');
    }

    toggleBtn.addEventListener('click', () => {
        body.classList.toggle('dark-mode');
        const isDark = body.classList.contains('dark-mode');

        // Toggle icon
        if (isDark) {
            themeIcon.classList.remove('fa-moon');
            themeIcon.classList.add('fa-sun');
        } else {
            themeIcon.classList.remove('fa-sun');
            themeIcon.classList.add('fa-moon');
        }

        // Save preference
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
    });
</script>



</body>

</html>