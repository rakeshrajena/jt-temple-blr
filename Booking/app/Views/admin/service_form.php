<?php /** @var array $s @var array $slots @var array $types */
$chk = fn (bool $v) => $v ? ' checked' : '';
$fields = $s['fields'];
for ($i = count($fields); $i < count($fields) + 3; $i++) { $extra[] = $i; }
$wd = ['1' => 'Mon', '2' => 'Tue', '3' => 'Wed', '4' => 'Thu', '5' => 'Fri', '6' => 'Sat', '7' => 'Sun'];
?>
<p><a href="<?= e(url('/admin/services')) ?>">← Services</a></p>
<h1><?= e($title) ?></h1>
<form method="post" action="<?= e(url('/admin/services/save')) ?>" class="panel form">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
  <div class="grid-2">
    <label>Service name <input name="name" required maxlength="150" value="<?= e($s['name']) ?>"></label>
    <label>Price (₹)<?= $s['is_food_only'] ? ' — not used for Food-Only' : '' ?> <input name="price" inputmode="decimal" value="<?= e(number_format($s['price_paise'] / 100, 2, '.', '')) ?>"<?= $s['is_food_only'] ? ' disabled' : '' ?>><small class="muted">Use 0 when the temple confirms charges later (add them on the booking page).</small></label>
  </div>
  <label>Description shown to devotees <textarea name="description" rows="2" maxlength="500"><?= e((string) $s['description']) ?></textarea></label>
  <fieldset class="checks">
    <legend>Rules</legend>
    <label class="inline"><input type="checkbox" name="is_active" value="1"<?= $chk($s['is_active']) ?>> Available for online booking</label>
    <label class="inline"><input type="checkbox" name="requires_approval" value="1"<?= $chk($s['requires_approval']) ?>> Needs temple approval before confirmation</label>
    <label class="inline"><input type="checkbox" name="requires_slot" value="1"<?= $chk($s['requires_slot']) ?>> Devotee must choose a time slot</label>
    <label class="inline"><input type="checkbox" name="collect_participants" value="1"<?= $chk($s['collect_participants']) ?>> Ask number of participants</label>
    <?php if (!$s['is_food_only']): ?>
    <label class="inline"><input type="checkbox" name="allow_food" value="1"<?= $chk($s['allow_food']) ?>> Allow food / prasadam coupons with this service</label>
    <label class="inline"><input type="checkbox" name="is_combinable" value="1"<?= $chk($s['is_combinable']) ?>> Can be booked together with other services</label>
    <?php endif; ?>
  </fieldset>
  <div class="grid-3">
    <label>Max participants <input type="number" name="max_participants" min="1" value="<?= (int) $s['max_participants'] ?>"></label>
    <label>Max bookings per day <span class="muted">(empty = no limit)</span><input type="number" name="daily_capacity" min="0" value="<?= e((string) ($s['daily_capacity'] ?? '')) ?>"></label>
    <label>Minimum notice in days <span class="muted">(empty = default)</span><input type="number" name="lead_days" min="0" value="<?= e((string) ($s['lead_days'] ?? '')) ?>"></label>
    <label>Display order <input type="number" name="sort_order" value="<?= (int) $s['sort_order'] ?>"></label>
  </div>

  <fieldset>
    <legend>Extra details to collect</legend>
    <p class="muted small">Leave the label empty to remove a field. For a choice list, enter options separated by commas.</p>
    <table class="table compact fields-table">
      <thead><tr><th>Label</th><th>Type</th><th>Required</th><th>Options (choice list)</th></tr></thead>
      <tbody>
      <?php foreach ($fields as $i => $fd): ?>
        <tr><td><input type="hidden" name="field_key[<?= $i ?>]" value="<?= e($fd['key']) ?>"><input name="field_label[<?= $i ?>]" value="<?= e($fd['label']) ?>" maxlength="80"></td>
          <td><select name="field_type[<?= $i ?>]"><?php foreach ($types as $k => $l): ?><option value="<?= $k ?>"<?= $fd['type'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></td>
          <td class="center"><input type="checkbox" name="field_required[<?= $i ?>]" value="1"<?= $chk(!empty($fd['required'])) ?>></td>
          <td><input name="field_options[<?= $i ?>]" value="<?= e(implode(', ', $fd['options'] ?? [])) ?>"></td></tr>
      <?php endforeach; ?>
      <?php foreach ($extra as $i): ?>
        <tr><td><input name="field_label[<?= $i ?>]" maxlength="80" placeholder="New field label"></td>
          <td><select name="field_type[<?= $i ?>]"><?php foreach ($types as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?></select></td>
          <td class="center"><input type="checkbox" name="field_required[<?= $i ?>]" value="1"></td>
          <td><input name="field_options[<?= $i ?>]"></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </fieldset>
  <button class="btn btn-primary" type="submit">Save service</button>
</form>

<?php if ($s['id']): ?>
<section class="panel" id="slots">
  <h2>Time slots</h2>
  <p class="muted small">Capacity = how many bookings can take this slot on one date (empty = unlimited). Untick weekdays on which the slot is not offered.</p>
  <?php foreach (array_merge($slots, [['id' => 0, 'label' => '', 'start_time' => '', 'capacity' => null, 'weekdays' => '1234567', 'is_active' => true, 'sort_order' => count($slots)]]) as $sl): ?>
  <form method="post" action="<?= e(url('/admin/slots/save')) ?>" class="slot-row">
    <?= csrf_field() ?>
    <input type="hidden" name="service_id" value="<?= (int) $s['id'] ?>"><input type="hidden" name="id" value="<?= (int) $sl['id'] ?>">
    <label>Label <input name="label" value="<?= e($sl['label']) ?>" maxlength="100" placeholder="<?= $sl['id'] ? '' : 'New slot, e.g. Morning (7–9 AM)' ?>"></label>
    <label>Starts <input type="time" name="start_time" value="<?= e($sl['start_time']) ?>"></label>
    <label>Capacity <input type="number" name="capacity" min="0" value="<?= e((string) ($sl['capacity'] ?? '')) ?>"></label>
    <span class="days"><?php foreach ($wd as $k => $l): ?><label class="inline"><input type="checkbox" name="weekdays[]" value="<?= $k ?>"<?= $chk(str_contains($sl['weekdays'], $k)) ?>><?= $l ?></label><?php endforeach; ?></span>
    <label class="inline"><input type="checkbox" name="is_active" value="1"<?= $chk((bool) $sl['is_active']) ?>> Active</label>
    <input type="hidden" name="sort_order" value="<?= (int) $sl['sort_order'] ?>">
    <button class="btn btn-small<?= $sl['id'] ? '' : ' btn-primary' ?>" type="submit"><?= $sl['id'] ? 'Save' : 'Add slot' ?></button>
  </form>
  <?php endforeach; ?>
</section>
<?php endif; ?>
