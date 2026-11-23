/* ==========================================================================
   Olli CMS — Admin Panel & Visual Page/Column/Block Builder Logic
   ========================================================================== */

const OlliAdmin = {
    state: window.OLLI_ADMIN_STATE || {},
    currentPage: null,
    currentPost: null,
    activeModalCol: 'main',
    editingBlockIndex: null,

    init() {
        if (!this.state.pages) return;
        this.renderPagesList();
        if (this.state.pages.length > 0) {
            this.loadPageIntoEditor(this.state.pages[0].slug);
        } else {
            this.createNewPage();
        }

        this.renderPostsList();
        if (this.state.posts.length > 0) {
            this.loadPostIntoEditor(this.state.posts[0].slug);
        } else {
            this.createNewPost();
        }

        this.renderMediaGrid();
        this.renderMessagesList();
        this.renderMenuRows();
        this.initSortableColumns();
    },

    toast(msg, isError = false) {
        const el = document.getElementById('adminToast');
        if (!el) return;
        el.style.color = isError ? '#dc2626' : '#059669';
        el.textContent = msg;
        setTimeout(() => { el.textContent = ''; }, 3500);
    },

    async apiPost(action, data) {
        const res = await fetch('api.php?action=' + encodeURIComponent(action), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.state.csrfToken
            },
            body: JSON.stringify(data)
        });
        return await res.json();
    },

    switchTab(tabId) {
        document.querySelectorAll('.admin-tab').forEach(s => s.classList.remove('active'));
        document.querySelectorAll('.side-nav-btn').forEach(b => b.classList.remove('active'));

        const sec = document.getElementById('tab-' + tabId);
        const btn = document.querySelector(`.side-nav-btn[data-tab="${tabId}"]`);
        if (sec) sec.classList.add('active');
        if (btn) btn.classList.add('active');

        const titles = {
            dashboard: 'Yleiskatsaus',
            pages: 'Sivut, Palstoitukset & Sisältölohkot',
            posts: 'Artikkelit & Tiedotteet',
            theme: 'Ulkoasu, Värit & Teemapaketit',
            media: 'Mediapankki & Liitetiedostot',
            messages: 'Saapuneet yhteydenotot',
            settings: 'Valikot, Globaalit lohkot & Asetukset'
        };
        const titleEl = document.getElementById('topbarTitle');
        if (titleEl && titles[tabId]) titleEl.textContent = titles[tabId];
    },

    /* ======================================================================
       1. SIVUT, PALSTOITUS & LOHKOT (VISUAL BUILDER)
       ====================================================================== */

    renderPagesList() {
        const container = document.getElementById('pagesListContainer');
        if (!container) return;
        container.innerHTML = '';
        this.state.pages.forEach(p => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'list-row-btn' + (this.currentPage && this.currentPage.slug === p.slug ? ' active' : '');
            const layoutName = this.state.layouts[p.layout]?.name || p.layout;
            btn.innerHTML = `
                <strong>${this.esc(p.title)}</strong>
                <small>/${this.esc(p.slug)} • ${this.esc(layoutName)}</small>
            `;
            btn.onclick = () => this.loadPageIntoEditor(p.slug);
            container.appendChild(btn);
        });
    },

    loadPageIntoEditor(slug) {
        const found = this.state.pages.find(p => p.slug === slug);
        if (!found) return;
        this.currentPage = JSON.parse(JSON.stringify(found));
        if (!this.currentPage.columns) {
            this.currentPage.columns = { left: [], main: [], right: [] };
        }
        this.populatePageEditorFields();
        this.renderPagesList();
    },

    createNewPage() {
        this.currentPage = {
            title: 'Uusi sivu',
            slug: 'uusi-sivu-' + Math.floor(Math.random() * 900 + 100),
            subtitle: '',
            status: 'published',
            order: this.state.pages.length + 1,
            layout: '3col-moodle',
            show_global_left: true,
            show_global_right: false,
            columns: {
                left: [],
                main: [
                    {
                        id: 'blk-' + Math.random().toString(36).substring(2, 8),
                        type: 'richtext',
                        title: 'Tervetuloa uudelle sivulle',
                        style: 'surface',
                        content: '<p>Tämä on uuden sivun sisältölohko. Voit lisätä palstoihin uusia elementtejä tai vaihtaa palstoitusta yläpuolelta.</p>'
                    }
                ],
                right: []
            }
        };
        this.populatePageEditorFields();
        this.renderPagesList();
    },

    populatePageEditorFields() {
        const p = this.currentPage;
        document.getElementById('pageTitle').value = p.title || '';
        document.getElementById('pageSlug').value = p.slug || '';
        document.getElementById('pageSubtitle').value = p.subtitle || '';
        document.getElementById('pageStatus').value = p.status || 'published';
        document.getElementById('pageOrder').value = p.order || 1;
        document.getElementById('pageGlobalLeft').checked = !!p.show_global_left;
        document.getElementById('pageGlobalRight').checked = !!p.show_global_right;
        document.getElementById('pagePreviewLink').href = '../index.php?page=' + encodeURIComponent(p.slug || '');

        this.selectPageLayout(p.layout || '3col-moodle');
        this.renderBuilderColumns();
    },

    autoSlugPage() {
        const title = document.getElementById('pageTitle').value;
        const slugInput = document.getElementById('pageSlug');
        if (!slugInput.value || slugInput.value.startsWith('uusi-sivu')) {
            slugInput.value = this.slugify(title);
        }
    },

    selectPageLayout(layoutKey) {
        if (!this.currentPage) return;
        this.currentPage.layout = layoutKey;
        document.querySelectorAll('.layout-option-card').forEach(btn => {
            btn.classList.toggle('active', btn.getAttribute('data-layout') === layoutKey);
        });

        const activeCols = this.state.layouts[layoutKey]?.cols || ['left', 'main', 'right'];
        document.querySelectorAll('.builder-col').forEach(colEl => {
            const cKey = colEl.getAttribute('data-col');
            colEl.classList.toggle('inactive-col', !activeCols.includes(cKey));
        });
    },

    initSortableColumns() {
        if (typeof Sortable === 'undefined') return;
        ['left', 'main', 'right'].forEach(colKey => {
            const el = document.getElementById('colList-' + colKey);
            if (!el) return;
            new Sortable(el, {
                group: 'admin-builder-cols',
                animation: 160,
                onEnd: () => this.syncBuilderDomToState()
            });
        });
    },

    renderBuilderColumns() {
        ['left', 'main', 'right'].forEach(colKey => {
            const listEl = document.getElementById('colList-' + colKey);
            if (!listEl) return;
            listEl.innerHTML = '';
            const blocks = this.currentPage.columns[colKey] || [];
            blocks.forEach((blk, idx) => {
                const catInfo = this.state.blockCatalog[blk.type] || { icon: '📦', name: blk.type };
                const chip = document.createElement('div');
                chip.className = 'builder-block-chip';
                chip.setAttribute('data-block-json', JSON.stringify(blk));
                chip.innerHTML = `
                    <div class="chip-info">
                        <strong>${catInfo.icon} ${this.esc(blk.title || catInfo.name)}</strong>
                        <small>${this.esc(catInfo.name)} • ${this.esc(blk.style || 'surface')}</small>
                    </div>
                    <div class="chip-actions">
                        <button type="button" title="Muokkaa" onclick="OlliAdmin.openEditBlock('${colKey}', ${idx})">✏️</button>
                        <button type="button" title="Poista" onclick="OlliAdmin.removeBlock('${colKey}', ${idx})">🗑️</button>
                    </div>
                `;
                listEl.appendChild(chip);
            });
        });
    },

    syncBuilderDomToState() {
        ['left', 'main', 'right'].forEach(colKey => {
            const listEl = document.getElementById('colList-' + colKey);
            if (!listEl) return;
            const arr = [];
            listEl.querySelectorAll('.builder-block-chip').forEach(chip => {
                try {
                    arr.push(JSON.parse(chip.getAttribute('data-block-json')));
                } catch (e) {}
            });
            this.currentPage.columns[colKey] = arr;
        });
        this.renderBuilderColumns();
    },

    removeBlock(colKey, idx) {
        this.currentPage.columns[colKey].splice(idx, 1);
        this.renderBuilderColumns();
    },

    openBlockPicker(colKey) {
        this.activeModalCol = colKey;
        this.editingBlockIndex = null;
        const modal = document.getElementById('adminBlockModal');
        const title = document.getElementById('adminModalTitle');
        const body = document.getElementById('adminModalBody');

        title.textContent = 'Valitse sisältöelementti palstaan (' + colKey + ')';
        let html = '<div class="preset-cards-grid">';
        for (const [type, info] of Object.entries(this.state.blockCatalog)) {
            html += `
                <div class="preset-card" onclick="OlliAdmin.openBlockForm('${type}', null)">
                    <div style="font-size:1.6rem;margin-bottom:0.35rem;">${info.icon}</div>
                    <strong>${this.esc(info.name)}</strong>
                    <small>${this.esc(info.desc)}</small>
                </div>
            `;
        }
        html += '</div>';
        body.innerHTML = html;
        modal.style.display = 'flex';
    },

    openEditBlock(colKey, idx) {
        this.activeModalCol = colKey;
        this.editingBlockIndex = idx;
        const blk = this.currentPage.columns[colKey][idx];
        document.getElementById('adminBlockModal').style.display = 'flex';
        this.openBlockForm(blk.type, blk);
    },

    openBlockForm(type, existingBlock) {
        const titleEl = document.getElementById('adminModalTitle');
        const body = document.getElementById('adminModalBody');
        const info = this.state.blockCatalog[type] || { icon: '📦', name: type };

        const data = existingBlock || this.createDefaultBlock(type);
        titleEl.textContent = `${info.icon} ${info.name}`;

        let html = `
            <form id="adminBlockForm" onsubmit="return OlliAdmin.saveBlockFromModal(event, '${type}')" style="display:flex;flex-direction:column;gap:0.9rem;">
                <div class="form-grid-3">
                    <label>Lohkon otsikko
                        <input type="text" name="title" value="${this.escAttr(data.title || '')}">
                    </label>
                    <label>Yläkulman tunniste (Badge)
                        <input type="text" name="badge" value="${this.escAttr(data.badge || '')}" placeholder="esim. Ohje / Uusi">
                    </label>
                    <label>Kortin väriteema
                        <select name="style">
                            <option value="surface" ${data.style === 'surface' ? 'selected' : ''}>Peruskortti (Surface)</option>
                            <option value="primary" ${data.style === 'primary' ? 'selected' : ''}>Päävärikorostus</option>
                            <option value="accent" ${data.style === 'accent' ? 'selected' : ''}>Sivuvärikorostus</option>
                            <option value="gradient" ${data.style === 'gradient' ? 'selected' : ''}>Liukuväri (Hero)</option>
                            <option value="dark" ${data.style === 'dark' ? 'selected' : ''}>Tumma kortti</option>
                        </select>
                    </label>
                </div>
        `;

        if (['richtext', 'hero', 'callout', 'code'].includes(type)) {
            html += `
                <label>Tekstisisältö (voit käyttää **lihavointia** tai HTML-merkintää)
                    <textarea name="content" rows="6">${this.esc(data.content || '')}</textarea>
                </label>`;
        }

        if (type === 'callout') {
            html += `
                <div class="form-grid-3">
                    <label>Kuvake / Emoji
                        <input type="text" name="icon" value="${this.escAttr(data.icon || '💡')}">
                    </label>
                    <label>Painikkeen teksti
                        <input type="text" name="button_text" value="${this.escAttr(data.button_text || '')}">
                    </label>
                    <label>Painikkeen linkki (URL)
                        <input type="text" name="button_url" value="${this.escAttr(data.button_url || '')}">
                    </label>
                </div>`;
        }

        if (type === 'hero') {
            html += `
                <div class="form-grid-2">
                    <label>Pääpainikkeen teksti
                        <input type="text" name="button_text" value="${this.escAttr(data.button_text || '')}">
                    </label>
                    <label>Pääpainikkeen URL
                        <input type="text" name="button_url" value="${this.escAttr(data.button_url || '')}">
                    </label>
                    <label>Toissijaisen painikkeen teksti
                        <input type="text" name="secondary_text" value="${this.escAttr(data.secondary_text || '')}">
                    </label>
                    <label>Toissijaisen painikkeen URL
                        <input type="text" name="secondary_url" value="${this.escAttr(data.secondary_url || '')}">
                    </label>
                </div>`;
        }

        if (type === 'post_feed') {
            html += `
                <div class="form-grid-3">
                    <label>Kategoria (all = kaikki)
                        <input type="text" name="category" value="${this.escAttr(data.category || 'all')}">
                    </label>
                    <label>Näytettävä määrä
                        <input type="number" name="limit" value="${Number(data.limit || 3)}" min="1" max="12">
                    </label>
                    <label>Asettelu
                        <select name="layout_mode">
                            <option value="cards" ${data.layout_mode === 'cards' ? 'selected' : ''}>Korttiruudukko</option>
                            <option value="compact" ${data.layout_mode === 'compact' ? 'selected' : ''}>Kompakti lista</option>
                        </select>
                    </label>
                </div>`;
        }

        if (type === 'embed') {
            html += `
                <label>Videon tai upotteen URL (esim. YouTube)
                    <input type="text" name="embed_url" value="${this.escAttr(data.embed_url || '')}">
                </label>
                <label>Kuvateksti
                    <input type="text" name="caption" value="${this.escAttr(data.caption || '')}">
                </label>`;
        }

        if (type === 'contact_form') {
            html += `
                <label>Johdantoteksti lomakkeen yllä
                    <input type="text" name="description" value="${this.escAttr(data.description || '')}">
                </label>
                <label>Lähetyspainikkeen teksti
                    <input type="text" name="submit_text" value="${this.escAttr(data.submit_text || 'Lähetä viesti')}">
                </label>`;
        }

        // Visuaalinen rivieditori monirivisille lohkoille (accordion, links, files, gallery, stats)
        if (['accordion', 'links', 'files', 'gallery', 'stats'].includes(type)) {
            html += `
                <div>
                    <div class="section-label-row">
                        <strong>Lohkon alkiot / rivit:</strong>
                        <button type="button" class="btn btn-xs btn-outline" onclick="OlliAdmin.addRepeaterRow('${type}')">＋ Lisää uusi rivi</button>
                    </div>
                    <div id="blockRepeaterContainer"></div>
                </div>
            `;
        }

        html += `
                <div style="display:flex;justify-content:flex-end;gap:0.75rem;margin-top:0.75rem;">
                    <button type="button" class="btn btn-outline" onclick="OlliAdmin.closeBlockModal()">Peruuta</button>
                    <button type="submit" class="btn btn-primary">💾 Valmis</button>
                </div>
            </form>
        `;

        body.innerHTML = html;

        if (['accordion', 'links', 'files', 'gallery', 'stats'].includes(type)) {
            (data.items || []).forEach(item => this.addRepeaterRow(type, item));
        }
    },

    addRepeaterRow(type, item = {}) {
        const container = document.getElementById('blockRepeaterContainer');
        if (!container) return;
        const row = document.createElement('div');
        row.className = 'repeater-row';

        if (type === 'accordion') {
            row.innerHTML = `
                <input type="text" data-field="question" placeholder="Otsikko / Kysymys" value="${this.escAttr(item.question || '')}">
                <input type="text" data-field="answer" placeholder="Sisältö / Vastaus" value="${this.escAttr(item.answer || '')}">
                <button type="button" class="btn btn-xs btn-danger" onclick="this.parentElement.remove()">✕</button>
            `;
        } else if (type === 'links') {
            row.style.gridTemplateColumns = '70px 1fr 1fr auto';
            row.innerHTML = `
                <input type="text" data-field="icon" placeholder="🔗" value="${this.escAttr(item.icon || '🔗')}">
                <input type="text" data-field="label" placeholder="Linkin teksti" value="${this.escAttr(item.label || '')}">
                <input type="text" data-field="url" placeholder="Osoite (URL)" value="${this.escAttr(item.url || '#')}">
                <button type="button" class="btn btn-xs btn-danger" onclick="this.parentElement.remove()">✕</button>
            `;
        } else if (type === 'files') {
            row.style.gridTemplateColumns = '1fr 110px 1fr auto';
            row.innerHTML = `
                <input type="text" data-field="name" placeholder="Tiedoston nimi" value="${this.escAttr(item.name || '')}">
                <input type="text" data-field="size" placeholder="Koko (esim. 1.2 Mt)" value="${this.escAttr(item.size || '')}">
                <input type="text" data-field="url" placeholder="Tiedoston URL" value="${this.escAttr(item.url || '#')}">
                <button type="button" class="btn btn-xs btn-danger" onclick="this.parentElement.remove()">✕</button>
            `;
        } else if (type === 'gallery') {
            row.innerHTML = `
                <input type="text" data-field="url" placeholder="Kuvan URL (content/uploads/...)" value="${this.escAttr(item.url || '')}">
                <input type="text" data-field="caption" placeholder="Kuvateksti" value="${this.escAttr(item.caption || '')}">
                <button type="button" class="btn btn-xs btn-danger" onclick="this.parentElement.remove()">✕</button>
            `;
        } else if (type === 'stats') {
            row.innerHTML = `
                <input type="text" data-field="value" placeholder="Arvo (esim. 99 %)" value="${this.escAttr(item.value || '')}">
                <input type="text" data-field="label" placeholder="Selite" value="${this.escAttr(item.label || '')}">
                <button type="button" class="btn btn-xs btn-danger" onclick="this.parentElement.remove()">✕</button>
            `;
        }
        container.appendChild(row);
    },

    saveBlockFromModal(event, type) {
        event.preventDefault();
        const fd = new FormData(event.target);
        const existing = this.editingBlockIndex !== null
            ? this.currentPage.columns[this.activeModalCol][this.editingBlockIndex]
            : null;

        const blockObj = {
            id: existing?.id || ('blk-' + Math.random().toString(36).substring(2, 8)),
            type: type
        };

        for (const [k, v] of fd.entries()) {
            blockObj[k] = v;
        }

        const repContainer = document.getElementById('blockRepeaterContainer');
        if (repContainer) {
            const items = [];
            repContainer.querySelectorAll('.repeater-row').forEach(r => {
                const obj = {};
                r.querySelectorAll('[data-field]').forEach(inp => {
                    obj[inp.getAttribute('data-field')] = inp.value;
                });
                items.push(obj);
            });
            blockObj.items = items;
        }

        if (this.editingBlockIndex !== null) {
            this.currentPage.columns[this.activeModalCol][this.editingBlockIndex] = blockObj;
        } else {
            this.currentPage.columns[this.activeModalCol].push(blockObj);
        }

        this.renderBuilderColumns();
        this.closeBlockModal();
        return false;
    },

    createDefaultBlock(type) {
        switch (type) {
            case 'callout':
                return { type, title: 'Tärkeä huomio', badge: 'Info', style: 'primary', icon: '💡', content: 'Kirjoita huomioteksti tähän.' };
            case 'accordion':
                return { type, title: 'Usein kysytyt kysymykset', style: 'surface', items: [{ question: 'Kysymys tai aihe 1', answer: 'Vastaus tai sisältö 1' }] };
            case 'links':
                return { type, title: 'Pikalinkit', style: 'surface', items: [{ icon: '🔗', label: 'Uusi linkki', url: '#' }] };
            case 'files':
                return { type, title: 'Materiaali & Tiedostot', style: 'surface', items: [{ name: 'Esitys.pdf', size: '1.0 Mt', url: '#' }] };
            case 'stats':
                return { type, title: 'Tilastot', style: 'surface', items: [{ value: '100 %', label: 'Tavoite' }] };
            case 'gallery':
                return { type, title: 'Kuvagalleria', style: 'surface', columns: 2, items: [{ url: 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?auto=format&fit=crop&w=800&q=80', caption: 'Kuva' }] };
            default:
                return { type, title: 'Otsikko', style: 'surface', content: '<p>Sisältöteksti...</p>' };
        }
    },

    closeBlockModal() {
        document.getElementById('adminBlockModal').style.display = 'none';
    },

    async saveCurrentPage() {
        if (!this.currentPage) return;
        this.syncBuilderDomToState();
        this.currentPage.title = document.getElementById('pageTitle').value.trim();
        this.currentPage.slug = document.getElementById('pageSlug').value.trim();
        this.currentPage.subtitle = document.getElementById('pageSubtitle').value.trim();
        this.currentPage.status = document.getElementById('pageStatus').value;
        this.currentPage.order = Number(document.getElementById('pageOrder').value || 1);
        this.currentPage.show_global_left = document.getElementById('pageGlobalLeft').checked;
        this.currentPage.show_global_right = document.getElementById('pageGlobalRight').checked;

        const res = await this.apiPost('save_page', this.currentPage);
        if (res.ok) {
            this.currentPage.slug = res.slug;
            const idx = this.state.pages.findIndex(p => p.slug === res.slug);
            if (idx !== -1) {
                this.state.pages[idx] = JSON.parse(JSON.stringify(this.currentPage));
            } else {
                this.state.pages.push(JSON.parse(JSON.stringify(this.currentPage)));
            }
            this.renderPagesList();
            this.toast('✓ Sivu ja palstarakenne tallennettu!');
        } else {
            this.toast(res.error || 'Tallennus epäonnistui', true);
        }
    },

    async deleteCurrentPage() {
        if (!this.currentPage || !confirm('Poistetaanko tämä sivu pysyvästi?')) return;
        const slug = this.currentPage.slug;
        const res = await this.apiPost('delete_page', { slug });
        if (res.ok) {
            this.state.pages = this.state.pages.filter(p => p.slug !== slug);
            if (this.state.pages.length > 0) {
                this.loadPageIntoEditor(this.state.pages[0].slug);
            } else {
                this.createNewPage();
            }
            this.toast('Sivu poistettu.');
        }
    },

    /* ======================================================================
       2. ARTIKKELIT (POSTS)
       ====================================================================== */

    renderPostsList() {
        const container = document.getElementById('postsListContainer');
        if (!container) return;
        container.innerHTML = '';
        this.state.posts.forEach(post => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'list-row-btn' + (this.currentPost && this.currentPost.slug === post.slug ? ' active' : '');
            btn.innerHTML = `
                <strong>${this.esc(post.title)}</strong>
                <small>${this.esc(post.date || '')} • ${this.esc(post.category || 'Yleinen')}</small>
            `;
            btn.onclick = () => this.loadPostIntoEditor(post.slug);
            container.appendChild(btn);
        });
    },

    loadPostIntoEditor(slug) {
        const found = this.state.posts.find(p => p.slug === slug);
        if (!found) return;
        this.currentPost = JSON.parse(JSON.stringify(found));
        this.populatePostFields();
        this.renderPostsList();
    },

    createNewPost() {
        this.currentPost = {
            title: 'Uusi artikkeli',
            slug: 'uusi-artikkeli-' + Math.floor(Math.random() * 900 + 100),
            category: 'Uutiset',
            date: new Date().toISOString().slice(0, 10),
            author: 'Pääkäyttäjä',
            read_time: '3 min',
            status: 'published',
            featured_image: '',
            excerpt: 'Lyhyt tiivistelmä artikkelin sisällöstä...',
            content: '<p>Kirjoita artikkelin varsinainen teksti tähän...</p>'
        };
        this.populatePostFields();
        this.renderPostsList();
    },

    populatePostFields() {
        const p = this.currentPost;
        document.getElementById('postTitle').value = p.title || '';
        document.getElementById('postSlug').value = p.slug || '';
        document.getElementById('postCategory').value = p.category || '';
        document.getElementById('postDate').value = p.date || '';
        document.getElementById('postAuthor').value = p.author || '';
        document.getElementById('postReadTime').value = p.read_time || '';
        document.getElementById('postStatus').value = p.status || 'published';
        document.getElementById('postImage').value = p.featured_image || '';
        document.getElementById('postExcerpt').value = p.excerpt || '';
        document.getElementById('postContent').value = p.content || '';
    },

    autoSlugPost() {
        const title = document.getElementById('postTitle').value;
        const slugInput = document.getElementById('postSlug');
        if (!slugInput.value || slugInput.value.startsWith('uusi-artikkeli')) {
            slugInput.value = this.slugify(title);
        }
    },

    insertPostTag(openTag, closeTag) {
        const ta = document.getElementById('postContent');
        if (!ta) return;
        const start = ta.selectionStart;
        const end = ta.selectionEnd;
        const selected = ta.value.substring(start, end) || 'Teksti';
        ta.value = ta.value.substring(0, start) + openTag + selected + closeTag + ta.value.substring(end);
        ta.focus();
    },

    async saveCurrentPost() {
        if (!this.currentPost) return;
        this.currentPost.title = document.getElementById('postTitle').value.trim();
        this.currentPost.slug = document.getElementById('postSlug').value.trim();
        this.currentPost.category = document.getElementById('postCategory').value.trim();
        this.currentPost.date = document.getElementById('postDate').value;
        this.currentPost.author = document.getElementById('postAuthor').value.trim();
        this.currentPost.read_time = document.getElementById('postReadTime').value.trim();
        this.currentPost.status = document.getElementById('postStatus').value;
        this.currentPost.featured_image = document.getElementById('postImage').value.trim();
        this.currentPost.excerpt = document.getElementById('postExcerpt').value.trim();
        this.currentPost.content = document.getElementById('postContent').value;

        const res = await this.apiPost('save_post', this.currentPost);
        if (res.ok) {
            this.currentPost.slug = res.slug;
            const idx = this.state.posts.findIndex(p => p.slug === res.slug);
            if (idx !== -1) {
                this.state.posts[idx] = JSON.parse(JSON.stringify(this.currentPost));
            } else {
                this.state.posts.unshift(JSON.parse(JSON.stringify(this.currentPost)));
            }
            this.renderPostsList();
            this.toast('✓ Artikkeli tallennettu!');
        } else {
            this.toast(res.error || 'Tallennus epäonnistui', true);
        }
    },

    async deleteCurrentPost() {
        if (!this.currentPost || !confirm('Poistetaanko tämä artikkeli?')) return;
        const slug = this.currentPost.slug;
        const res = await this.apiPost('delete_post', { slug });
        if (res.ok) {
            this.state.posts = this.state.posts.filter(p => p.slug !== slug);
            if (this.state.posts.length > 0) {
                this.loadPostIntoEditor(this.state.posts[0].slug);
            } else {
                this.createNewPost();
            }
            this.toast('Artikkeli poistettu.');
        }
    },

    /* ======================================================================
       3. ULKOASU & TEEMA (THEME)
       ====================================================================== */

    async applyThemePreset(presetKey) {
        const res = await this.apiPost('save_theme', { apply_preset: presetKey });
        if (res.ok && res.theme) {
            const t = res.theme;
            document.getElementById('themePrimary').value = t.primary_color;
            document.getElementById('themeAccent').value = t.accent_color;
            document.getElementById('themeBg').value = t.bg_color;
            document.getElementById('themeSurface').value = t.surface_color;
            document.getElementById('themeText').value = t.text_color;
            document.getElementById('themeMode').value = t.mode;
            document.getElementById('themeFontHeading').value = t.font_heading;
            document.getElementById('themeFontBody').value = t.font_body;
            document.getElementById('themeRadius').value = t.border_radius;
            document.getElementById('themeGap').value = t.grid_gap;
            document.getElementById('themeWidth').value = t.container_width;
            document.getElementById('themeCardStyle').value = t.card_style;
            this.toast('✓ Teemapaketti "' + t.name + '" otettu käyttöön!');
        }
    },

    async saveThemeConfig() {
        const payload = {
            primary_color: document.getElementById('themePrimary').value,
            accent_color: document.getElementById('themeAccent').value,
            bg_color: document.getElementById('themeBg').value,
            surface_color: document.getElementById('themeSurface').value,
            text_color: document.getElementById('themeText').value,
            mode: document.getElementById('themeMode').value,
            font_heading: document.getElementById('themeFontHeading').value,
            font_body: document.getElementById('themeFontBody').value,
            border_radius: document.getElementById('themeRadius').value,
            grid_gap: document.getElementById('themeGap').value,
            container_width: document.getElementById('themeWidth').value,
            card_style: document.getElementById('themeCardStyle').value,
            header_style: 'glass'
        };
        const res = await this.apiPost('save_theme', payload);
        if (res.ok) {
            this.toast('✓ Ulkoasuasetukset tallennettu!');
        }
    },

    /* ======================================================================
       4. MEDIAPANKKI & VIESTIT & ASETUKSET
       ====================================================================== */

    renderMediaGrid() {
        const container = document.getElementById('mediaGridContainer');
        if (!container) return;
        if (!this.state.media || this.state.media.length === 0) {
            container.innerHTML = '<p class="text-muted">Mediapankissa ei ole vielä ladattuja tiedostoja.</p>';
            return;
        }
        container.innerHTML = '';
        this.state.media.forEach(item => {
            const card = document.createElement('div');
            card.className = 'media-card';
            const thumb = item.is_image
                ? `<img src="../${this.escAttr(item.url)}" alt="${this.escAttr(item.filename)}">`
                : `<span style="font-size:2.2rem;">📄</span>`;
            card.innerHTML = `
                <div class="media-thumb">${thumb}</div>
                <div class="media-meta">
                    <strong title="${this.escAttr(item.filename)}">${this.esc(item.filename)}</strong>
                    <small>${this.esc(item.size_human)}</small>
                    <div style="display:flex;gap:0.35rem;margin-top:0.5rem;">
                        <button type="button" class="btn btn-xs btn-outline" onclick="navigator.clipboard.writeText('${this.escAttr(item.url)}');OlliAdmin.toast('Osoite kopioitu leikepöydälle!')">📋 Kopioi URL</button>
                        <button type="button" class="btn btn-xs btn-danger" onclick="OlliAdmin.deleteMedia('${this.escAttr(item.filename)}')">🗑️</button>
                    </div>
                </div>
            `;
            container.appendChild(card);
        });
    },

    async uploadMediaFile(input) {
        if (!input.files || !input.files[0]) return;
        const fd = new FormData();
        fd.append('file', input.files[0]);
        fd.append('csrf_token', this.state.csrfToken);

        const res = await fetch('api.php?action=upload_media', {
            method: 'POST',
            headers: { 'X-CSRF-Token': this.state.csrfToken },
            body: fd
        });
        const data = await res.json();
        if (data.ok) {
            this.state.media.unshift({
                filename: data.filename,
                url: data.url,
                size_human: 'Juuri ladattu',
                is_image: /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(data.filename)
            });
            this.renderMediaGrid();
            this.toast('✓ Tiedosto ladattu!');
        } else {
            this.toast(data.error || 'Virhe latauksessa', true);
        }
        input.value = '';
    },

    async deleteMedia(filename) {
        if (!confirm('Poistetaanko tiedosto ' + filename + '?')) return;
        const res = await this.apiPost('delete_media', { filename });
        if (res.ok) {
            this.state.media = this.state.media.filter(m => m.filename !== filename);
            this.renderMediaGrid();
            this.toast('Tiedosto poistettu.');
        }
    },

    renderMessagesList() {
        const container = document.getElementById('messagesListContainer');
        if (!container) return;
        if (!this.state.messages || this.state.messages.length === 0) {
            container.innerHTML = '<p class="text-muted">Ei saapuneita viestejä.</p>';
            return;
        }
        container.innerHTML = '';
        this.state.messages.forEach(msg => {
            const div = document.createElement('div');
            div.className = 'panel-card';
            div.style.marginBottom = '0.85rem';
            div.innerHTML = `
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <strong>${this.esc(msg.subject || 'Viesti')} — ${this.esc(msg.name)} (${this.esc(msg.email)})</strong>
                    <div style="display:flex;gap:0.5rem;align-items:center;">
                        <small>${this.esc(msg.created_at)}</small>
                        <button type="button" class="btn btn-xs btn-danger" onclick="OlliAdmin.deleteMessage('${this.escAttr(msg.id)}')">Poista</button>
                    </div>
                </div>
                <p style="margin:0.5rem 0 0 0;white-space:pre-wrap;">${this.esc(msg.message)}</p>
            `;
            container.appendChild(div);
        });
    },

    async deleteMessage(id) {
        const res = await this.apiPost('delete_message', { id });
        if (res.ok) {
            this.state.messages = this.state.messages.filter(m => m.id !== id);
            this.renderMessagesList();
            this.toast('Viesti poistettu.');
        }
    },

    renderMenuRows() {
        const container = document.getElementById('menuRowsContainer');
        if (!container) return;
        container.innerHTML = '';
        (this.state.siteConfig.menu || []).forEach(item => this.addMenuRow(item));
    },

    addMenuRow(item = { icon: '🔸', label: 'Uusi linkki', url: '?page=etusivu' }) {
        const container = document.getElementById('menuRowsContainer');
        if (!container) return;
        const row = document.createElement('div');
        row.className = 'repeater-row';
        row.style.gridTemplateColumns = '65px 1fr 1fr auto';
        row.innerHTML = `
            <input type="text" data-menu="icon" value="${this.escAttr(item.icon || '')}" placeholder="Ikoni">
            <input type="text" data-menu="label" value="${this.escAttr(item.label || '')}" placeholder="Valikon teksti">
            <input type="text" data-menu="url" value="${this.escAttr(item.url || '')}" placeholder="Osoite (URL)">
            <button type="button" class="btn btn-xs btn-danger" onclick="this.parentElement.remove()">✕</button>
        `;
        container.appendChild(row);
    },

    async saveSiteSettings() {
        const menu = [];
        document.querySelectorAll('#menuRowsContainer .repeater-row').forEach(r => {
            menu.push({
                icon: r.querySelector('[data-menu="icon"]').value.trim(),
                label: r.querySelector('[data-menu="label"]').value.trim(),
                url: r.querySelector('[data-menu="url"]').value.trim()
            });
        });

        let globalBlocks = this.state.siteConfig.global_blocks || { left: [], right: [] };
        try {
            globalBlocks = JSON.parse(document.getElementById('cfgGlobalBlocks').value);
        } catch (e) {
            this.toast('Globaalien lohkojen JSON-muoto on virheellinen!', true);
            return;
        }

        const payload = {
            site_name: document.getElementById('cfgSiteName').value.trim(),
            tagline: document.getElementById('cfgTagline').value.trim(),
            homepage_slug: document.getElementById('cfgHomepage').value.trim(),
            footer_text: document.getElementById('cfgFooter').value.trim(),
            menu: menu,
            global_blocks: globalBlocks
        };

        const res = await this.apiPost('save_site_config', payload);
        if (res.ok) {
            this.state.siteConfig = payload;
            this.toast('✓ Sivuston asetukset ja valikot tallennettu!');
        }
    },

    async changePassword() {
        const pass = document.getElementById('newAdminPassword').value;
        const res = await this.apiPost('change_password', { new_password: pass });
        if (res.ok) {
            document.getElementById('newAdminPassword').value = '';
            this.toast('✓ Salasana päivitetty!');
        } else {
            this.toast(res.error || 'Virhe salasanan vaihdossa', true);
        }
    },

    slugify(str) {
        return String(str)
            .toLowerCase()
            .replace(/[äå]/g, 'a')
            .replace(/ö/g, 'o')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    },

    esc(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    },

    escAttr(str) {
        return this.esc(str).replace(/"/g, '&quot;');
    }
};

document.addEventListener('DOMContentLoaded', () => {
    OlliAdmin.init();
});
