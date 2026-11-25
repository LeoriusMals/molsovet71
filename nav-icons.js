(()=>{'use strict';
const paths={
'Обзор':'<rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="5" rx="1.5"/><rect x="13" y="10" width="8" height="11" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/>',
'Отчёты на утверждение':'<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h8"/>',
'Отчёты на проверке':'<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/>',
'Отчёты':'<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h8"/>',
'Заявки':'<path d="M4 4h16v16H4z"/><path d="m8 12 2.5 2.5L16 9"/>',
'Рейтинг':'<path d="M4 19V9M10 19V5M16 19v-7M22 19V3"/>',
'Опросы':'<path d="M8 6h13M8 12h13M8 18h13"/><path d="M3 6h.01M3 12h.01M3 18h.01"/>',
'Муниципалитеты':'<path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6M9 9h.01M15 9h.01M9 12h.01M15 12h.01"/>',
'Мероприятия':'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
'Доска задач':'<rect x="4" y="3" width="16" height="18" rx="2"/><path d="m8 10 2 2 4-4M8 16h8"/>',
'Идеи и обращения':'<path d="M9 18h6M10 22h4M8 14c-1.3-1-2-2.5-2-4a6 6 0 1 1 12 0c0 1.5-.7 3-2 4-.8.7-1 1.3-1 2h-6c0-.7-.2-1.3-1-2Z"/>',
'Учётные записи':'<circle cx="9" cy="8" r="4"/><path d="M3 21v-2a6 6 0 0 1 12 0v2M19 8v6M16 11h6"/>',
'Список участников':'<circle cx="9" cy="8" r="4"/><path d="M2 21v-2a7 7 0 0 1 14 0v2M17 4a4 4 0 0 1 0 8M19 15a5 5 0 0 1 3 4v2"/>',
'Департамент Цифровизации':'<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 21V9h6v12M7 7h.01M17 7h.01M7 11h.01M17 11h.01"/>',
'Роли и права':'<path d="M12 3 4 6v5c0 5 3.4 8.5 8 10 4.6-1.5 8-5 8-10V6z"/><path d="m9 12 2 2 4-4"/>',
'Статистика':'<path d="M3 3v18h18M7 14l4-4 3 3 6-7"/>',
'Настройки':'<circle cx="12" cy="12" r="3"/><path d="m19.4 15 .1.1 1.4 1.1-1.4 2.4-1.7-.7a8 8 0 0 1-1.6.9L16 21h-8l-.2-2.2a8 8 0 0 1-1.6-.9l-1.7.7-1.4-2.4 1.4-1.1a8 8 0 0 1 0-2l-1.4-1.1 1.4-2.4 1.7.7a8 8 0 0 1 1.6-.9L8 3h8l.2 2.2a8 8 0 0 1 1.6.9l1.7-.7 1.4 2.4-1.4 1.1a8 8 0 0 1 0 2Z"/>',
'Обзор':'<rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="5" rx="1.5"/><rect x="13" y="10" width="8" height="11" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/>',
'Мои задачи':'<rect x="4" y="3" width="16" height="18" rx="2"/><path d="m8 10 2 2 4-4M8 16h8"/>',
'Мои отчёты':'<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h6"/>',
'Мои заявки':'<rect x="4" y="3" width="16" height="18" rx="2"/><path d="m8 10 2 2 4-4M8 16h8"/>',
'Мой рейтинг':'<path d="M4 19V9M10 19V5M16 19v-7M22 19V3"/>',
'Мои мероприятия':'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
'Профиль':'<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
'Уведомления':'<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
'Выход':'<path d="M10 17l5-5-5-5M15 12H3M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/>',
'Комиссии':'<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
'Участники':'<circle cx="9" cy="8" r="4"/><path d="M2 21v-2a7 7 0 0 1 14 0v2M17 4a4 4 0 0 1 0 8M19 15a5 5 0 0 1 3 4v2"/>'
};
const aliases=[['Отчёты на утверждение','Отчёты на утверждение'],['Отчёты на проверке','Отчёты на проверке'],['Мои мероприятия','Мои мероприятия'],['Мои отчёты','Мои отчёты'],['Мои задачи','Мои задачи'],['Мои заявки','Мои заявки'],['Мой рейтинг','Мой рейтинг'],['Список участников','Список участников'],['Учётные записи','Учётные записи'],['Департамент Цифровизации','Департамент Цифровизации'],['Идеи и обращения','Идеи и обращения'],['Муниципалитеты','Муниципалитеты'],['Мероприятия','Мероприятия'],['Рейтинг','Рейтинг'],['Опросы','Опросы'],['Заявки','Заявки'],['Отчёты','Отчёты'],['Доска задач','Доска задач'],['Настройки','Настройки'],['Статистика','Статистика'],['Роли и права','Роли и права'],['Комиссии','Комиссии'],['Участники','Участники'],['Профиль','Профиль'],['Уведомления','Уведомления'],['Выход','Выход'],['Обзор','Обзор']];
document.querySelectorAll('.side .nav a,.side .nav button').forEach(el=>{if(el.querySelector('.nav-icon'))return;let label='';for(const [needle,key] of aliases){if(el.textContent.includes(needle)){label=key;break}}if(!label)return;const first=[...el.childNodes].find(n=>n.nodeType===3);if(first)first.textContent=first.textContent.replace(/^[\s\u2600-\u27ff\u2300-\u23ff♙☷▥⚿▤★▣⌖✉✦▦⚙]+/u,'');const icon=document.createElement('span');icon.className='nav-icon';icon.setAttribute('aria-hidden','true');icon.innerHTML='<svg viewBox="0 0 24 24">'+(paths[label]||paths['Обзор'])+'</svg>';el.insertBefore(icon,el.firstChild);const textNode=[...el.childNodes].find(n=>n.nodeType===3&&n.textContent.trim());if(textNode){const span=document.createElement('span');span.className='nav-text';span.textContent=textNode.textContent.trim();el.replaceChild(span,textNode)}});

})();
