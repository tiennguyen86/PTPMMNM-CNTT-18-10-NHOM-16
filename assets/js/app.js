/* API SchemaCheck – tương tác phía trình duyệt (không phụ thuộc thư viện) */
(function () {
    'use strict';

    // Xác nhận trước khi gửi form nguy hiểm (xóa...)
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!window.confirm(form.dataset.confirm)) e.preventDefault();
        });
    });

    // Tự gửi form khi đổi lựa chọn (đổi quyền thành viên...)
    document.querySelectorAll('select[data-autosubmit]').forEach(function (sel) {
        sel.addEventListener('change', function () { sel.form.submit(); });
    });

    // Kiểm tra cú pháp JSON ngay khi gõ
    function checkJson(textarea) {
        var status = document.querySelector('[data-status-for="' + textarea.id + '"]');
        if (!status) return;
        var text = textarea.value.trim();
        if (!text) { status.textContent = ''; status.className = 'json-status'; return; }
        try {
            JSON.parse(text);
            status.textContent = '✓ JSON hợp lệ';
            status.className = 'json-status ok';
        } catch (err) {
            status.textContent = '✗ ' + err.message;
            status.className = 'json-status bad';
        }
    }
    document.querySelectorAll('textarea[data-json]').forEach(function (ta) {
        var timer;
        ta.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () { checkJson(ta); }, 250);
        });
        // Cho phép gõ Tab trong ô nhập JSON
        ta.addEventListener('keydown', function (e) {
            if (e.key !== 'Tab' || e.shiftKey) return;
            e.preventDefault();
            var s = ta.selectionStart;
            ta.setRangeText('  ', s, ta.selectionEnd, 'end');
        });
        checkJson(ta);
    });

    // Định dạng (pretty-print) JSON
    document.querySelectorAll('[data-format]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var ta = document.getElementById(btn.dataset.format);
            try {
                ta.value = JSON.stringify(JSON.parse(ta.value), null, 2);
                checkJson(ta);
            } catch (err) {
                checkJson(ta);
            }
        });
    });

    // Đọc tệp được chọn vào ô nhập để xem trước
    document.querySelectorAll('input[type=file][data-load-into]').forEach(function (input) {
        input.addEventListener('change', function () {
            var file = input.files[0];
            var label = input.closest('.file-drop');
            if (!file) return;
            var reader = new FileReader();
            reader.onload = function () {
                var ta = document.getElementById(input.dataset.loadInto);
                ta.value = String(reader.result).replace(/^﻿/, '');
                checkJson(ta);
                if (label) {
                    label.classList.add('loaded');
                    label.querySelector('span').textContent = 'Đã nạp: ' + file.name + ' (' + Math.ceil(file.size / 1024) + ' KB)';
                }
            };
            reader.readAsText(file, 'UTF-8');
        });
    });

    // Tabs
    document.querySelectorAll('[data-tabs]').forEach(function (root) {
        root.querySelectorAll('[data-tab]').forEach(function (tab) {
            tab.addEventListener('click', function () {
                root.querySelectorAll('[data-tab]').forEach(function (t) { t.classList.toggle('active', t === tab); });
                root.querySelectorAll('[data-panel]').forEach(function (p) {
                    p.classList.toggle('active', p.dataset.panel === tab.dataset.tab);
                });
            });
        });
    });

    // Lọc bảng thay đổi theo mức độ / phần
    document.querySelectorAll('[data-filter-group]').forEach(function (group) {
        var table = document.getElementById(group.dataset.filterGroup);
        if (!table) return;
        var state = { sev: 'all', part: null };
        function apply() {
            table.querySelectorAll('tbody tr').forEach(function (tr) {
                var ok = (state.sev === 'all' || tr.dataset.sev === state.sev) && (!state.part || tr.dataset.part === state.part);
                tr.hidden = !ok;
            });
        }
        group.querySelectorAll('[data-filter]').forEach(function (chip) {
            chip.addEventListener('click', function () {
                state.sev = chip.dataset.filter;
                group.querySelectorAll('[data-filter]').forEach(function (c) { c.classList.toggle('active', c === chip); });
                apply();
            });
        });
        group.querySelectorAll('[data-filter-part]').forEach(function (chip) {
            chip.addEventListener('click', function () {
                state.part = state.part === chip.dataset.filterPart ? null : chip.dataset.filterPart;
                group.querySelectorAll('[data-filter-part]').forEach(function (c) {
                    c.classList.toggle('active', c.dataset.filterPart === state.part);
                });
                apply();
            });
        });
    });

    // In báo cáo
    document.querySelectorAll('[data-print]').forEach(function (btn) {
        btn.addEventListener('click', function () { window.print(); });
    });

    // Checkbox "chọn tất cả" (import OpenAPI)
    document.querySelectorAll('[data-check-all]').forEach(function (master) {
        master.addEventListener('change', function () {
            var name = master.dataset.checkAll;
            document.querySelectorAll('input[type=checkbox][name="' + name + '"]').forEach(function (cb) {
                cb.checked = master.checked;
            });
        });
    });
})();
