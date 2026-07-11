<?php
require_once __DIR__ . '/init.php';
Auth::requirePermission('add_incident');

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$incidentObj = new Incident();
$problems = $incidentObj->getProblems();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Add Incident</title>
    <style>
        body{
            font-family: Arial, sans-serif;
            background:#f5f7fb;
            margin:0;
            padding:20px;
        }
        .container{
            max-width:1200px;
            margin:0 auto;
        }
        .card{
            background:#fff;
            border-radius:10px;
            padding:20px;
            box-shadow:0 2px 10px rgba(0,0,0,.08);
        }
        h2{
            margin-top:0;
            margin-bottom:18px;
        }
        .form-grid{
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:16px 20px;
        }
        .field-block label{
            display:block;
            font-size:14px;
            font-weight:700;
            margin-bottom:6px;
            color:#111827;
        }
        .field-block input,
        .field-block select{
            width:100%;
            min-height:42px;
            padding:10px 12px;
            border:1px solid #cbd5e1;
            border-radius:8px;
            box-sizing:border-box;
            font-size:14px;
            background:#fff;
        }
        .field-block input[readonly]{
            background:#f8fafc;
        }
        .full-width{
            grid-column:1 / -1;
        }
        .btn-row{
            margin-top:18px;
            display:flex;
            gap:10px;
            flex-wrap:wrap;
        }
        .btn{
            display:inline-block;
            padding:10px 14px;
            border:none;
            border-radius:8px;
            text-decoration:none;
            cursor:pointer;
            font-size:14px;
            font-weight:700;
        }
        .btn-save{
            background:#2563eb;
            color:#fff;
        }
        .btn-back{
            background:#6b7280;
            color:#fff;
        }
        .note{
            margin-top:5px;
            font-size:12px;
            color:#64748b;
        }
        @media (max-width: 900px){
            .form-grid{
                grid-template-columns:1fr;
            }
        }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container">
    <div class="card">
        <h2>Add Incident</h2>
<?php if (isset($_GET['duplicate'])): ?>
    <div style="
        background:#f8d7da;
        color:#842029;
        padding:10px;
        border-radius:6px;
        margin-bottom:10px;">
        ⚠️ This ATM already has an open incident.
    </div>
<?php endif; ?>
        <form method="POST" action="save_incident.php" id="incidentForm">
            <input type="hidden" name="problem_vendor_mapping" id="problem_vendor_mapping" value="">

            <div class="form-grid">

                <div class="field-block">
                    <label>ATM ID</label>
                    <input type="text" id="atm_id" name="atm_id"
value="<?php echo htmlspecialchars($_GET['atm_id'] ?? ''); ?>">
                </div>

                <div class="field-block">
                    <label>ATM Name</label>
                    <input type="text" name="atm_name" id="atm_name" readonly>
                </div>

                <div class="field-block">
                    <label>Zone</label>
                    <input type="text" name="zone_name" id="zone_name" readonly>
                </div>

                <div class="field-block">
                    <label>Branch</label>
                    <input type="text" name="branch_name" id="branch_name" readonly>
                </div>

                <div class="field-block">
                    <label>Group No</label>
                    <input type="text" name="group_no" id="group_no" readonly>
                </div>

                <div class="field-block">
                    <label>Machine Type</label>
                    <input type="text" name="machine_type" id="machine_type" readonly>
                </div>

                <div class="field-block">
                    <label>Problem</label>
                    <select name="problem" id="problem" required>
                        <option value="">Select Problem</option>
                        <?php while($p = $problems->fetch_assoc()) { ?>
                            <option value="<?= h($p['problem_name']) ?>">
                                <?= h($p['problem_name']) ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="field-block">
                    <label>ATM Vendor</label>
                    <input type="text" name="atm_vendor" id="atm_vendor" readonly>
                </div>

                <div class="field-block">
                    <label>UPS Vendor</label>
                    <input type="text" name="ups_vendor" id="ups_vendor" readonly>
                </div>

                <div class="field-block">
                    <label>Responsible Vendor</label>
                    <input type="text" name="responsible_vendor_name" id="responsible_vendor_name">
                    <div class="note">Problem অনুযায়ী auto-fill হবে, চাইলে manually change করতে পারবেন</div>
                </div>

                <div class="field-block full-width">
                    <label>Down Time</label>
                    <input type="text" name="down_time" id="down_time" placeholder="e.g. 1 hour / 24 min / 2 hour 5 min">
                </div>

            </div>

            <div class="btn-row">
                <button type="submit" class="btn btn-save">Save Incident</button>
                <a href="dashboard_ajax_v2.php" class="btn btn-back">Back</a>
            </div>
        </form>
    </div>
</div>

<script>
function setField(id, value) {
    const el = document.getElementById(id);
    if (el) {
        el.value = value || '';
    }
}

function fetchAtmInfo() {
    const atmId = document.getElementById('atm_id').value.trim();

    if (!atmId) {
        setField('atm_name', '');
        setField('zone_name', '');
        setField('branch_name', '');
        setField('group_no', '');
        setField('machine_type', '');
        setField('atm_vendor', '');
        setField('ups_vendor', '');
        applyResponsibleVendor();
        return;
    }

    fetch('get_atm_info.php?atm_id=' + encodeURIComponent(atmId))
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                setField('atm_name', '');
                setField('zone_name', '');
                setField('branch_name', '');
                setField('group_no', '');
                setField('machine_type', '');
                setField('atm_vendor', '');
                setField('ups_vendor', '');
                applyResponsibleVendor();
                return;
            }

            setField('atm_name', data.atm_name);
            setField('zone_name', data.zone_name);
            setField('branch_name', data.branch_name);
            setField('group_no', data.group_no);
            setField('machine_type', data.machine_type);
            setField('atm_vendor', data.atm_vendor);
            setField('ups_vendor', data.ups_vendor);

            applyResponsibleVendor();
        })
        .catch(() => {
            setField('atm_name', '');
            setField('zone_name', '');
            setField('branch_name', '');
            setField('group_no', '');
            setField('machine_type', '');
            setField('atm_vendor', '');
            setField('ups_vendor', '');
            applyResponsibleVendor();
        });
}

function fetchProblemVendorMapping() {
    const problem = document.getElementById('problem').value.trim();

    if (!problem) {
        setField('problem_vendor_mapping', '');
        applyResponsibleVendor();
        return;
    }

    fetch('get_problem_vendor_type.php?problem_name=' + encodeURIComponent(problem))
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                setField('problem_vendor_mapping', '');
                applyResponsibleVendor();
                return;
            }

            const mappedType = (data.responsible_vendor_type || '').trim();
            setField('problem_vendor_mapping', mappedType);

            // Mapping আসার পর responsible vendor আবার calculate
            applyResponsibleVendor();
        })
        .catch(() => {
            setField('problem_vendor_mapping', '');
            applyResponsibleVendor();
        });
}

function applyResponsibleVendor() {
    const rawMapped = (document.getElementById('problem_vendor_mapping').value || '').trim();
    const mappedValue = rawMapped.toUpperCase();

    const atmVendor = (document.getElementById('atm_vendor').value || '').trim();
    const upsVendor = (document.getElementById('ups_vendor').value || '').trim();

    let finalVendor = '';

    if (mappedValue === 'UPS') {
        finalVendor = upsVendor;
    } else if (mappedValue === 'ATM' || mappedValue === 'CRM') {
        finalVendor = atmVendor;
    } else if (rawMapped !== '') {
        // direct vendor name mapping থাকলে
        finalVendor = rawMapped;
    } else if (atmVendor !== '') {
        finalVendor = atmVendor;
    } else {
        finalVendor = upsVendor;
    }

    setField('responsible_vendor_name', finalVendor);
}

let debounceTimer = null;

document.getElementById('atm_id').addEventListener('keyup', function () {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(fetchAtmInfo, 400);
});

document.getElementById('atm_id').addEventListener('change', function () {
    fetchAtmInfo();
});

document.getElementById('problem').addEventListener('change', function () {
    fetchProblemVendorMapping();
});

document.addEventListener('DOMContentLoaded', function () {
    const atmId = document.getElementById('atm_id').value.trim();
    const problem = document.getElementById('problem').value.trim();

    if (atmId !== '') {
        fetchAtmInfo();
    }
    if (problem !== '') {
        fetchProblemVendorMapping();
    }
});
</script>
</body>
</html>
