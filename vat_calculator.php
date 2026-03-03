<?php
$pageTitle = "Tính VAT & VAT ngược";
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="card p-4">
                <h4 class="text-center mb-4">
                    <i class="bi bi-calculator"></i> TÍNH VAT & VAT NGƯỢC
                </h4>

                <div class="mb-3">
                    <label class="form-label">💰 Số tiền</label>
                    <input type="number" id="amount" class="form-control" placeholder="VD: 47000">
                </div>

                <div class="mb-3">
                    <label class="form-label">📌 Loại giá</label>
                    <select id="type" class="form-select">
                        <option value="included">Giá đã gồm VAT (VAT ngược)</option>
                        <option value="excluded">Giá chưa VAT</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">📊 VAT (%)</label>
                    <div class="input-group">
                        <select id="vatSelect" class="form-select">
                            <option value="5">5%</option>
                            <option value="8" selected>8%</option>
                            <option value="10">10%</option>
                            <option value="custom">Tùy nhập</option>
                        </select>
                        <input type="number" id="vatCustom" class="form-control d-none" placeholder="Nhập % VAT">
                    </div>
                </div>

                <div class="result-box mt-4">
                    <div>Giá chưa VAT: <span id="before">0.00</span> đ</div>
                    <div>VAT: <span id="vat">0.00</span> đ</div>
                    <div>Giá đã VAT: <span id="after">0.00</span> đ</div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
function calculateVAT() {
    let amount = parseFloat(document.getElementById('amount').value) || 0;
    let type = document.getElementById('type').value;

    let vatSelect = document.getElementById('vatSelect').value;
    let vatRate = vatSelect === 'custom'
        ? parseFloat(document.getElementById('vatCustom').value) || 0
        : parseFloat(vatSelect);

    let vatDecimal = vatRate / 100;
    let before = 0, vat = 0, after = 0;

    if (type === 'included') {
        before = amount / (1 + vatDecimal);
        vat = amount - before;
        after = amount;
    } else {
        before = amount;
        vat = before * vatDecimal;
        after = before + vat;
    }

    document.getElementById('before').innerText = before.toFixed(2);
    document.getElementById('vat').innerText = vat.toFixed(2);
    document.getElementById('after').innerText = after.toFixed(2);
}

document.querySelectorAll('#amount, #type, #vatSelect, #vatCustom')
    .forEach(el => el.addEventListener('input', calculateVAT));

document.getElementById('vatSelect').addEventListener('change', function () {
    document.getElementById('vatCustom').classList.toggle(
        'd-none',
        this.value !== 'custom'
    );
    calculateVAT();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
