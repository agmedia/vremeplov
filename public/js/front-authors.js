(function () {
    'use strict';

    var form = document.getElementById('author-search-form');
    var input = document.getElementById('author-search-input');
    var list = document.getElementById('author-search-suggestions');
    var status = document.getElementById('author-search-status');

    if (!form || !input || !list) {
        return;
    }

    var suggestUrl = form.getAttribute('data-suggest-url');
    var timer = null;
    var controller = null;
    var items = [];
    var activeIndex = -1;
    var lastQuery = '';

    function bookLabel(count) {
        var value = Number(count || 0);
        var lastTwo = value % 100;
        var last = value % 10;

        if (lastTwo >= 11 && lastTwo <= 14) {
            return value + ' knjiga';
        }

        if (last === 1) {
            return value + ' knjiga';
        }

        if (last >= 2 && last <= 4) {
            return value + ' knjige';
        }

        return value + ' knjiga';
    }

    function closeList() {
        list.classList.add('d-none');
        list.innerHTML = '';
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        items = [];
        activeIndex = -1;
        lastQuery = '';
    }

    function setStatus(message) {
        if (status) {
            status.textContent = message;
        }
    }

    function setActive(index) {
        if (!items.length) {
            return;
        }

        if (index < 0) {
            index = items.length - 1;
        } else if (index >= items.length) {
            index = 0;
        }

        items.forEach(function (item, itemIndex) {
            var active = itemIndex === index;
            item.classList.toggle('is-active', active);
            item.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        activeIndex = index;
        input.setAttribute('aria-activedescendant', items[index].id);
        items[index].scrollIntoView({ block: 'nearest' });
    }

    function messageRow(message) {
        list.innerHTML = '';
        items = [];
        activeIndex = -1;
        input.removeAttribute('aria-activedescendant');
        var row = document.createElement('div');
        row.className = 'author-suggestion author-suggestion--message';
        row.textContent = message;
        list.appendChild(row);
        list.classList.remove('d-none');
        input.setAttribute('aria-expanded', 'true');
    }

    function render(authors) {
        list.innerHTML = '';
        items = [];
        activeIndex = -1;

        if (!authors.length) {
            messageRow('Nema dostupnih autora za ovaj upit.');
            setStatus('Nema pronađenih autora.');
            return;
        }

        authors.forEach(function (author, index) {
            var link = document.createElement('a');
            var name = document.createElement('span');
            var count = document.createElement('span');

            link.id = 'author-suggestion-' + index;
            link.className = 'author-suggestion';
            link.href = author.url;
            link.setAttribute('role', 'option');
            link.setAttribute('aria-selected', 'false');

            name.className = 'author-suggestion__name';
            name.textContent = author.title;
            count.className = 'author-suggestion__count';
            count.textContent = bookLabel(author.products_count);

            link.appendChild(name);
            link.appendChild(count);
            list.appendChild(link);
        });

        items = Array.prototype.slice.call(list.querySelectorAll('.author-suggestion[role="option"]'));
        list.classList.remove('d-none');
        input.setAttribute('aria-expanded', 'true');
        setStatus(authors.length + (authors.length === 1 ? ' prijedlog.' : ' prijedloga.'));
    }

    function fetchAuthors(query) {
        if (controller) {
            controller.abort();
        }

        controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        messageRow('Tražim autore…');

        var options = {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        };

        if (controller) {
            options.signal = controller.signal;
        }

        fetch(suggestUrl + '?q=' + encodeURIComponent(query), options)
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Autocomplete request failed.');
                }

                return response.json();
            })
            .then(function (payload) {
                if (input.value.trim() === query) {
                    render(payload && Array.isArray(payload.authors) ? payload.authors : []);
                }
            })
            .catch(function (error) {
                if (error.name !== 'AbortError') {
                    closeList();
                    setStatus('Prijedlozi trenutačno nisu dostupni.');
                }
            });
    }

    input.addEventListener('input', function () {
        var query = input.value.trim();
        window.clearTimeout(timer);

        if (query.length < 2) {
            lastQuery = '';
            closeList();
            setStatus('Upišite najmanje dva slova.');
            return;
        }

        lastQuery = query;
        timer = window.setTimeout(function () {
            fetchAuthors(query);
        }, 220);
    });

    input.addEventListener('focus', function () {
        var query = input.value.trim();

        if (query.length >= 2 && query !== lastQuery) {
            lastQuery = query;
            fetchAuthors(query);
        }
    });

    input.addEventListener('keydown', function (event) {
        if (event.key === 'ArrowDown' && items.length) {
            event.preventDefault();
            setActive(activeIndex + 1);
        } else if (event.key === 'ArrowUp' && items.length) {
            event.preventDefault();
            setActive(activeIndex - 1);
        } else if (event.key === 'Enter' && activeIndex >= 0 && items[activeIndex]) {
            event.preventDefault();
            window.location.assign(items[activeIndex].href);
        } else if (event.key === 'Escape') {
            closeList();
        }
    });

    document.addEventListener('click', function (event) {
        if (!form.contains(event.target)) {
            closeList();
        }
    });

    form.addEventListener('submit', function () {
        closeList();
    });
})();
