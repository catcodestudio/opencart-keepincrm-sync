<?php
$_['heading_title']   = 'KeepinCRM Sync';

$_['text_extension']  = 'Розширення';
$_['text_home']       = 'Головна';
$_['text_success']    = 'Налаштування збережено!';
$_['text_edit']       = 'Налаштування синхронізації з KeepinCRM';
$_['text_general']    = 'Загальні';
$_['text_connection'] = 'Підключення';
$_['text_on_create']  = 'При створенні замовлення';
$_['text_on_status']  = 'При зміні статусу';
$_['text_log_link']   = 'Журнал синхронізації';
$_['text_test_ok']    = 'Зʼєднання OK';
$_['text_secret_set'] = '(збережено — залиште порожнім, щоб не змінювати)';
$_['text_routing']    = 'Маршрутизація в CRM (необовʼязково)';
$_['text_log']        = 'Журнал синхронізації (останні 100)';
$_['text_empty']      = 'Записів немає.';

$_['entry_status']         = 'Статус модуля';
$_['entry_api_key']        = 'API-токен (X-Auth-Token)';
$_['entry_send_on']        = 'Коли надсилати';
$_['entry_trigger']        = 'Статус-тригер';
$_['entry_skip_zero']      = 'Пропускати позиції з нульовою ціною';
$_['entry_include_ship']   = 'Додавати вартість доставки';
$_['entry_retry']          = 'Повторні спроби (cron)';
$_['entry_max_attempts']   = 'Макс. спроб';
$_['entry_source']         = 'Мітка джерела';
$_['entry_funnel_id']      = 'ID воронки (funnel_id)';
$_['entry_stage_id']       = 'ID етапу (stage_id)';
$_['entry_source_id']      = 'ID джерела (source_id)';
$_['entry_responsible_id'] = 'ID відповідального';

$_['column_order']    = 'Замовлення';
$_['column_status']   = 'Статус';
$_['column_external'] = 'ID у KeepinCRM';
$_['column_attempts'] = 'Спроб';
$_['column_error']    = 'Помилка';
$_['column_updated']  = 'Оновлено';

$_['button_save'] = 'Зберегти';
$_['button_test'] = 'Перевірити зʼєднання';

$_['help_api_key'] = 'Кабінет KeepinCRM → Налаштування → Профіль компанії → вкладка API → «Створити». Токен передається у заголовку X-Auth-Token, зберігається у зашифрованому вигляді; порожнє поле = не змінювати наявний токен.';
$_['help_send_on'] = 'Створення надсилає одразу після оформлення; зміна статусу — коли замовлення отримає обраний статус.';
$_['help_routing'] = 'Залиште порожнім, щоб KeepinCRM обрав значення за замовчуванням. ID беруться з довідників кабінету (воронки, етапи, джерела, працівники).';

$_['error_permission'] = 'У вас немає прав керувати цим модулем!';
