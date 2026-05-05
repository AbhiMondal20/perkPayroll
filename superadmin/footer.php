 <footer class="main-footer d-flex justify-content-between align-items-center">
        <div>
            Powered by &copy;
            <a href="https://abhitechbot.in" target="_blank">AbhiTechBot</a>
            <script>document.write(new Date().getFullYear())</script>
            <a href="https://abhitechbot.in" target="_blank">Rhythm Payroll 1.0.1</a>
        </div>
        <div>
            <a href="privacy-policy" class="text-decoration-none">Privacy Policy</a>
        </div>
    </footer>
</div>

<style>
    #themeIcon.fa-moon { color: white; }
    #themeIcon.fa-sun { color: gold; }
    #suggestion-box {
        z-index: 9999;
        display: none;
        background: #fff;
        border: 1px solid #ddd;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('searchInput');
    const suggestionBox = document.getElementById('suggestion-box');

    if (searchInput && suggestionBox) {
        searchInput.addEventListener('keyup', function() {
            const query = this.value.trim();

            if (query.length > 1) {
                fetch('../payroll/admin/load/search_suggestion.php?q=' + encodeURIComponent(query))
                    .then(r => r.text())
                    .then(html => {
                        suggestionBox.innerHTML = html;
                        suggestionBox.style.display = 'block';
                    })
                    .catch(() => {
                        suggestionBox.style.display = 'none';
                    });
            } else {
                suggestionBox.style.display = 'none';
            }
        });

        document.addEventListener('click', function(e) {
            if (e.target.closest('.suggestion-item')) {
                const item = e.target.closest('.suggestion-item');
                const strong = item.querySelector('strong');
                if (strong) {
                    searchInput.value = strong.innerText;
                }
                suggestionBox.style.display = 'none';
            } else if (!e.target.closest('#searchInput')) {
                suggestionBox.style.display = 'none';
            }
        });
    }

    const toggleBtn = document.getElementById('themeToggle');
    const body = document.getElementById('body');
    const themeIcon = document.getElementById('themeIcon');

    if (localStorage.getItem('theme') === 'dark') {
        body.classList.add('dark-mode');
        themeIcon.classList.remove('fa-moon');
        themeIcon.classList.add('fa-sun');
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            body.classList.toggle('dark-mode');
            const isDark = body.classList.contains('dark-mode');

            if (isDark) {
                themeIcon.classList.remove('fa-moon');
                themeIcon.classList.add('fa-sun');
            } else {
                themeIcon.classList.remove('fa-sun');
                themeIcon.classList.add('fa-moon');
            }

            localStorage.setItem('theme', isDark ? 'dark' : 'light');
        });
    }

    if ($.fn.DataTable && !$.fn.DataTable.isDataTable('#example')) {
        $('#example').DataTable({
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            responsive: true
        });
    }
});

function deleteClientDatabase(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: 'This action cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'delete_client_db',
                    id: id
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: data.message,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire('Error', data.message || 'Something went wrong', 'error');
                }
            })
            .catch(() => {
                Swal.fire('Error', 'Request failed', 'error');
            });
        }
    });
}
</script>

<script src="../payroll/admin/assets/js/netCheck.js"></script>
<script src="../payroll/admin/assets/js/vendors.min.js"></script>
<script src="../payroll/admin/assets/vendor_components/datatable/datatables.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/echarts/6.0.0/echarts.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.inputmask/5.0.9/jquery.inputmask.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="../payroll/admin/assets/js/template.js"></script>
<script src="../payroll/admin/assets/js/pages/dashboard4.js"></script>
<script src="../payroll/admin/assets/js/pages/validation.js"></script>
<script src="../payroll/admin/assets/js/pages/form-validation.js"></script>
<script src="../payroll/admin/assets/js/pages/advanced-form-element.js"></script>
<script src="../payroll/admin/assets/js/pages/dashboard.js"></script>
<script src="../payroll/admin/assets/js/pages/data-table.js"></script>

</body>
</html>