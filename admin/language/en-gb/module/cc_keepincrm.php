<?php
$_['heading_title']   = 'KeepinCRM Sync';

$_['text_extension']  = 'Extensions';
$_['text_home']       = 'Home';
$_['text_success']    = 'Settings saved!';
$_['text_edit']       = 'KeepinCRM sync settings';
$_['text_general']    = 'General';
$_['text_connection'] = 'Connection';
$_['text_on_create']  = 'On order placement';
$_['text_on_status']  = 'On status change';
$_['text_log_link']   = 'Sync journal';
$_['text_test_ok']    = 'Connection OK';
$_['text_secret_set'] = '(saved — leave blank to keep unchanged)';
$_['text_routing']    = 'CRM routing (optional)';
$_['text_log']        = 'Sync journal (last 100)';
$_['text_empty']      = 'No records.';

$_['entry_status']         = 'Module status';
$_['entry_api_key']        = 'API token (X-Auth-Token)';
$_['entry_send_on']        = 'When to send';
$_['entry_trigger']        = 'Trigger status';
$_['entry_skip_zero']      = 'Skip zero-price line items';
$_['entry_include_ship']   = 'Include shipping cost';
$_['entry_retry']          = 'Retry failed pushes (cron)';
$_['entry_max_attempts']   = 'Max attempts';
$_['entry_source']         = 'Source label';
$_['entry_funnel_id']      = 'Funnel id (funnel_id)';
$_['entry_stage_id']       = 'Stage id (stage_id)';
$_['entry_source_id']      = 'Source id (source_id)';
$_['entry_responsible_id'] = 'Responsible id';

$_['column_order']    = 'Order';
$_['column_status']   = 'Status';
$_['column_external'] = 'KeepinCRM ID';
$_['column_attempts'] = 'Attempts';
$_['column_error']    = 'Error';
$_['column_updated']  = 'Updated';

$_['button_save'] = 'Save';
$_['button_test'] = 'Test connection';

$_['help_api_key'] = 'KeepinCRM account → Settings → Company profile → API tab → "Create". Sent in the X-Auth-Token header, stored encrypted; leave blank to keep the existing token.';
$_['help_send_on'] = 'Placement sends right after checkout; status change sends when the order reaches the selected status.';
$_['help_routing'] = 'Leave blank to let KeepinCRM pick defaults. Ids come from your account dictionaries (funnels, stages, sources, staff).';

$_['error_permission'] = 'You do not have permission to manage this module!';
