(function (window, document) {
    'use strict';

    function addSourceEditing(editor, sourceElement) {
        const editorRoot = editor.ui.view.element;
        const toolbarItems = editorRoot.querySelector('.ck-toolbar__items');
        const editorMain = editorRoot.querySelector('.ck-editor__main');

        if (! toolbarItems || ! editorMain) {
            return;
        }

        const separator = document.createElement('span');
        separator.className = 'ck ck-toolbar__separator admin-source-toolbar-separator';

        const sourceButton = document.createElement('button');
        sourceButton.type = 'button';
        sourceButton.className = 'ck ck-button admin-source-toggle';
        sourceButton.title = 'HTML izvor';
        sourceButton.setAttribute('aria-label', 'HTML izvor');
        sourceButton.setAttribute('aria-pressed', 'false');
        sourceButton.innerHTML = '<span class="admin-source-toggle__icon" aria-hidden="true">&lt;/&gt;</span>';

        const sourceField = document.createElement('textarea');
        sourceField.className = 'admin-source-editor';
        sourceField.setAttribute('aria-label', 'HTML izvor sadržaja');
        sourceField.setAttribute('spellcheck', 'false');

        let sourceMode = false;

        function showVisualEditor() {
            editor.setData(sourceField.value);
            sourceMode = false;
            editorRoot.classList.remove('admin-source-mode');
            sourceButton.classList.remove('ck-on');
            sourceButton.classList.add('ck-off');
            sourceButton.setAttribute('aria-pressed', 'false');
            editor.editing.view.focus();
        }

        function showSourceEditor() {
            sourceField.value = editor.getData();
            sourceMode = true;
            editorRoot.classList.add('admin-source-mode');
            sourceButton.classList.remove('ck-off');
            sourceButton.classList.add('ck-on');
            sourceButton.setAttribute('aria-pressed', 'true');
            sourceField.focus();
        }

        sourceButton.classList.add('ck-off');
        sourceButton.addEventListener('click', function () {
            if (sourceMode) {
                showVisualEditor();
            } else {
                showSourceEditor();
            }
        });

        const form = sourceElement.closest('form');

        if (form) {
            form.addEventListener('submit', function () {
                if (sourceMode) {
                    editor.setData(sourceField.value);
                }

                sourceElement.value = editor.getData();
            });
        }

        toolbarItems.appendChild(separator);
        toolbarItems.appendChild(sourceButton);
        editorMain.insertAdjacentElement('afterend', sourceField);
    }

    function create(selector, options) {
        const sourceElement = document.querySelector(selector);

        if (! sourceElement) {
            return Promise.reject(new Error('Rich text editor element was not found.'));
        }

        const pageHeader = document.getElementById('page-header');
        const editorToolbarOffset = pageHeader ? Math.ceil(pageHeader.getBoundingClientRect().height) : 0;
        const config = {
            toolbar: {
                viewportTopOffset: editorToolbarOffset,
            },
        };

        if (options && options.uploadUrl) {
            config.ckfinder = {
                uploadUrl: options.uploadUrl,
            };
        }

        return window.ClassicEditor.create(sourceElement, config).then(function (editor) {
            const stickyPanel = editor.ui.view.stickyPanel;

            if (stickyPanel) {
                stickyPanel.unbind('isActive');
                stickyPanel.isActive = true;
            }

            addSourceEditing(editor, sourceElement);

            return editor;
        });
    }

    window.VremeplovRichTextEditor = {
        create: create,
    };
})(window, document);
