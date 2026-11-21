// Protected, read-only UI. No credentials or directory records in storage.
const root = document.documentElement;
const allowed = root.dataset.allowed.split(',').filter(Boolean);
const base = new URL(root.dataset.baseUrl, location.origin);
const titles = { zones:'Zones', branches:'Branches', offices:'Official offices', roles:'Portal roles', permissions:'Permissions' };
const fields = {
  zones:['zone_name','zone_code','status'],
  branches:['branch_name','zone_name','locality','recognition_status','operational_status'],
  offices:['office_name','organisational_level','source_section','is_active'],
  roles:['role_name','role_code','status','requires_second_approval'],
  permissions:['permission_name','permission_key','risk_level','status'],
};
const $ = id => document.getElementById(id);
let current = allowed[0] || '';
let page = 1;
let total = 0;
let controller = null;
function display(value) {
  if (value === null || value === undefined || value === '') return '—';
  if (value === 1 || value === '1') return 'Yes';
  if (value === 0 || value === '0') return 'No';
  return String(value);
}
function nav() {
  $('directory-nav').replaceChildren();
  for (const kind of allowed) {
    const button = document.createElement('button');
    button.type = 'button';
    button.textContent = titles[kind];
    if (kind === current) button.setAttribute('aria-current', 'page');
    button.addEventListener('click', () => { current = kind; page = 1; nav(); load(); });
    $('directory-nav').append(button);
  }
}
function clear() {
  $('directory-head').replaceChildren();
  $('directory-body').replaceChildren();
  $('directory-table').hidden = true;
  $('previous').disabled = true;
  $('next').disabled = true;
}
async function load() {
  if (controller) controller.abort();
  controller = new AbortController();
  const signal = controller.signal;
  clear();
  $('directory-title').textContent = titles[current] || 'Directory';
  $('notice').textContent = 'Loading authorised records…';
  $('page-label').textContent = '';
  try {
    const url = new URL('api/v1/administration/directory/' + current, base);
    url.searchParams.set('page', String(page));
    url.searchParams.set('per_page', '20');
    const response = await fetch(url, { credentials:'same-origin', cache:'no-store', signal, headers:{ Accept:'application/json' } });
    const payload = await response.json();
    if (!response.ok || !payload.success) {
      $('notice').textContent = response.status === 401 ? 'Your session expired. Sign in again.'
        : response.status === 403 ? 'You do not have permission to view this section.'
        : 'The directory could not be loaded. Please try again.';
      return;
    }
    const data = payload.data;
    total = data.total;
    if (!data.items.length) {
      $('notice').textContent = 'No authorised records are available in this section.';
    } else {
      $('notice').textContent = total + ' authorised record(s).';
      for (const field of fields[current]) {
        const th = document.createElement('th');
        th.scope = 'col';
        th.textContent = field.replaceAll('_',' ').replace(/\b\w/g, c => c.toUpperCase());
        $('directory-head').append(th);
      }
      for (const item of data.items) {
        const tr = document.createElement('tr');
        for (const field of fields[current]) {
          const td = document.createElement('td');
          td.textContent = display(item[field]);
          tr.append(td);
        }
        $('directory-body').append(tr);
      }
      $('directory-table').hidden = false;
    }
    $('previous').disabled = page <= 1;
    $('next').disabled = page * data.per_page >= total;
    $('page-label').textContent = 'Page ' + page;
  } catch (error) {
    if (error.name !== 'AbortError') $('notice').textContent = 'Network error. Please check your connection and retry.';
  }
}
$('previous').addEventListener('click', () => { if (page > 1) { page--; load(); } });
$('next').addEventListener('click', () => { if (page * 20 < total) { page++; load(); } });
if (allowed.length) { nav(); load(); }
else $('notice').textContent = 'No authorised directory sections.';
