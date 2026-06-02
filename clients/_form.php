<?php if (!empty($errors)): ?>
    <div class="col-12">
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>
<div class="col-md-6">
    <label class="form-label" for="client_name">Client name *</label>
    <input class="form-control" id="client_name" name="client_name" required value="<?= e($client['client_name'] ?? '') ?>">
</div>
<div class="col-md-3">
    <label class="form-label" for="idno">IDNO</label>
    <input class="form-control" id="idno" name="idno" value="<?= e($client['idno'] ?? '') ?>">
</div>
<div class="col-md-3">
    <label class="form-label" for="legal_form">Legal form</label>
    <input class="form-control" id="legal_form" name="legal_form" value="<?= e($client['legal_form'] ?? '') ?>">
</div>
<div class="col-md-3">
    <label class="form-label" for="registration_date">Registration date</label>
    <input class="form-control" type="date" id="registration_date" name="registration_date" value="<?= e($client['registration_date'] ?? '') ?>">
</div>
<div class="col-md-5">
    <label class="form-label" for="activity_sector">Activity sector</label>
    <input class="form-control" id="activity_sector" name="activity_sector" value="<?= e($client['activity_sector'] ?? '') ?>">
</div>
<div class="col-md-2">
    <label class="form-label" for="caem_code">CAEM code</label>
    <input class="form-control" id="caem_code" name="caem_code" value="<?= e($client['caem_code'] ?? '') ?>">
</div>
<div class="col-md-4">
    <label class="form-label" for="phone">Phone</label>
    <input class="form-control" id="phone" name="phone" value="<?= e($client['phone'] ?? '') ?>">
</div>
<div class="col-md-4">
    <label class="form-label" for="email">Email</label>
    <input class="form-control" type="email" id="email" name="email" value="<?= e($client['email'] ?? '') ?>">
</div>
<div class="col-md-4">
    <label class="form-label" for="website">Website</label>
    <input class="form-control" type="url" id="website" name="website" value="<?= e($client['website'] ?? '') ?>" placeholder="https://example.md">
</div>
<div class="col-md-4">
    <label class="form-label" for="status">Status</label>
    <select class="form-select" id="status" name="status">
        <?php foreach ($statuses as $status): ?>
            <option value="<?= e($status) ?>" <?= selected_attr($client['status'] ?? 'active', $status) ?>><?= e($status) ?></option>
        <?php endforeach; ?>
    </select>
</div>
<div class="col-12">
    <label class="form-label" for="address">Address</label>
    <textarea class="form-control" id="address" name="address" rows="2"><?= e($client['address'] ?? '') ?></textarea>
</div>
<div class="col-12">
    <label class="form-label" for="notes">Notes</label>
    <textarea class="form-control" id="notes" name="notes" rows="3"><?= e($client['notes'] ?? '') ?></textarea>
</div>
