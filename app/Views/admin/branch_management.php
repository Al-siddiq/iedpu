<!doctype html>
<html lang="en" data-base-url="<?= esc(base_url(), 'attr') ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Branch Management | IEDPU</title>
<link rel="stylesheet" href="<?= esc(base_url('assets/css/branch-management.css'), 'attr') ?>">
<script type="module" src="<?= esc(base_url('assets/js/pages/branch-management.js'), 'attr') ?>"></script></head>
<body><header><a href="<?= esc(base_url(), 'attr') ?>">IEDPU Northern Zone</a><span>Organisation administration</span></header>
<main><p class="eyebrow">P3 · WP03-A</p><h1>Branch directory management</h1>
<p class="intro">Create and maintain branch records. New branches remain inactive until recognised and explicitly activated by the Committee Super Administrator.</p>
<div id="notice" role="status" aria-live="polite">Loading branch records…</div>
<section class="panel"><h2 id="form-title">Add a branch</h2><form id="branch-form">
<input type="hidden" name="branch_id">
<div class="grid">
<label>Project zone<select name="zone_id" required></select></label>
<label>State / FCT<select name="state_id"><option value="">Select geographical area</option></select></label>
<label>Branch name<input name="branch_name" maxlength="160" required></label>
<label>Branch code<input name="branch_code" maxlength="40"></label>
<label>Source / recognised name<input name="source_name" maxlength="160"></label>
<label>Locality<input name="locality" maxlength="160"></label>
<label>LGA / District<input name="lga_or_district" maxlength="160"></label>
<label>Office address<input name="office_address" maxlength="500"></label>
<label>Contact phone<input name="contact_phone" maxlength="40"></label>
<label>Contact email<input name="contact_email" type="email" maxlength="190"></label>
<label>Recognition status<select name="recognition_status"><option value="pending_confirmation">Pending confirmation</option><option value="provisional">Provisional</option><option value="approved">Approved / recognised</option><option value="not_recognised">Not recognised</option></select></label>
<label>Recognition reference<input name="recognition_reference" maxlength="150"></label>
<label>Recognition date<input name="recognition_date" type="date"></label>
<label class="wide">Notes<textarea name="notes" maxlength="5000"></textarea></label>
<label class="wide">Reason for this change<textarea name="reason" minlength="3" maxlength="500" required></textarea></label>
</div><div class="actions"><button class="primary" type="submit">Save branch</button><button id="reset" type="button">Clear form</button></div></form></section>
<section class="panel"><h2>Branch records</h2><div class="table-wrap"><table><thead><tr><th>Branch</th><th>Location</th><th>Recognition</th><th>Operational status</th><th>Actions</th></tr></thead><tbody id="branches"></tbody></table></div></section>
<section class="panel"><h2>Change history</h2><div id="history">Select a branch to inspect its history.</div></section>
<p class="footnote">All changes require the branches.manage permission, a current authenticated MFA session, a reason and an audit record. No office-holder or portal-role assignment is created by adding a branch.</p></main></body></html>