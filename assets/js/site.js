/* ==========================================================================
   Olli CMS — Public Site & Moodle-Style Inline Page Builder JS
   ========================================================================== */

const OlliSite = {
    openLightbox(figureEl) {
        const lb = document.getElementById('olliLightbox');
        const img = document.getElementById('olliLightboxImg');
        const cap = document.getElementById('olliLightboxCaption');
        if (!lb || !img || !figureEl) return;
        const url = figureEl.getAttribute('data-url') || '';
        const caption = figureEl.getAttribute('data-caption') || '';
        img.src = url;
        if (cap) cap.textContent = caption;
        lb.classList.add('open');
    },

    closeLightbox() {
        const lb = document.getElementById('olliLightbox');
        if (lb) lb.classList.remove('open');
    },

    copyCode(btn) {
        const codeEl = btn.closest('.code-block-wrap')?.querySelector('code');
        if (!codeEl) return;
        navigator.clipboard.writeText(codeEl.innerText).then(() => {
            const orig = btn.textContent;
            btn.textContent = 'Kopioitu! ✓';
            setTimeout(() => { btn.textContent = orig; }, 1800);
        });
    },

    async submitContactForm(event, form) {
        event.preventDefault();
        const statusEl = form.querySelector('.form-status');
        if (statusEl) statusEl.textContent = 'Lähetetään...';

        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());

        try {
            const res = await fetch('admin/api.php?action=submit_contact', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.ok) {
                if (statusEl) {
                    statusEl.style.color = '#10b981';
                    statusEl.textContent = '✓ Kiitos! Viestisi on tallennettu.';
                }
                form.reset();
            } else {
                if (statusEl) {
                    statusEl.style.color = '#ef4444';
                    statusEl.textContent = data.error || 'Lähetys epäonnistui.';
                }
            }
        } catch (err) {
            if (statusEl) statusEl.textContent = 'Verkkovirhe.';
        }
        return false;
    }
};

/* --- MOODLE-TYYLINEN SUORA SIVUNMUOKKAIN (INLINE EDITOR) --- */

const OlliInlineEditor = {
    targetColumn: 'main',
    editingBlockId: null,

    init() {
        if (!window.OLLI_PAGE_DATA || typeof Sortable === 'undefined') return;
        const lists = document.querySelectorAll('.column-blocks-sortable');
        lists.forEach(listEl => {
            new Sortable(listEl, {
                group: 'olli-page-columns',
                animation: 180,
                handle: '.block-drag-handle',
                ghostClass: 'sortable-ghost',
                onEnd: () => {
                    this.syncDomToPageData();
                    this.savePageStructure();
                }
            });
        });
    },

    syncDomToPageData() {
        if (!window.OLLI_PAGE_DATA.columns) {
            window.OLLI_PAGE_DATA.columns = { left: [], main: [], right: [] };
        }
        const lists = document.querySelectorAll('.column-blocks-sortable');
        lists.forEach(listEl => {
            const colKey = listEl.getAttribute('data-column');
            const blocks = [];
            listEl.querySelectorAll('.bento-block[data-block-json]').forEach(blkEl => {
                try {
                    const parsed = JSON.parse(blkEl.getAttribute('data-block-json'));
                    blocks.push(parsed);
                } catch (e) {}
            });
            window.OLLI_PAGE_DATA.columns[colKey] = blocks;
        });
    },

    async changePageLayout(newLayout) {
        window.OLLI_PAGE_DATA.layout = newLayout;
        await this.savePageStructure(true);
    },

    async savePageStructure(reloadAfter = false) {
        this.syncDomToPageData();
        const gLeft = document.getElementById('inlineGlobalLeft');
        const gRight = document.getElementById('inlineGlobalRight');
        if (gLeft) window.OLLI_PAGE_DATA.show_global_left = gLeft.checked;
        if (gRight) window.OLLI_PAGE_DATA.show_global_right = gRight.checked;

        const indicator = document.getElementById('inlineSaveIndicator');
        if (indicator) indicator.textContent = 'Tallennetaan...';

        try {
            const res = await fetch('admin/api.php?action=save_page', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.OLLI_CSRF_TOKEN || ''
                },
                body: JSON.stringify(window.OLLI_PAGE_DATA)
            });
            const data = await res.json();
            if (data.ok) {
                if (indicator) indicator.textContent = '✓ Tallennettu (' + new Date().toLocaleTimeString() + ')';
                if (reloadAfter) window.location.reload();
            } else {
                if (indicator) indicator.textContent = '⚠️ Virhe tallennuksessa';
            }
        } catch (e) {
            if (indicator) indicator.textContent = '⚠️ Verkkovirhe';
        }
    },

    openAddBlockModal(columnKey) {
        this.targetColumn = columnKey;
        this.editingBlockId = null;
        const modal = document.getElementById('inlineBlockModal');
        const title = document.getElementById('inlineModalTitle');
        const body = document.getElementById('inlineModalBody');
        if (!modal || !body) return;

        title.textContent = 'Valitse lisättävä sisältöelementti (' + columnKey + ')';
        const catalog = window.OLLI_BLOCK_CATALOG || {};
        let html = '<div class="block-picker-grid">';
        for (const [type, info] of Object.entries(catalog)) {
            html += `
                <button type="button" class="block-picker-card" onclick="OlliInlineEditor.showBlockForm('${type}', null)">
                    <span style="font-size:1.5rem">${info.icon}</span>
                    <strong>${info.name}</strong>
                    <small>${info.desc}</small>
                </button>`;
        }
        html += '</div>';
        body.innerHTML = html;
        modal.style.display = 'flex';
    },

    openEditModal(btnEl) {
        const blockEl = btnEl.closest('.bento-block');
        if (!blockEl) return;
        this.targetColumn = blockEl.getAttribute('data-column') || 'main';
        const blockData = JSON.parse(blockEl.getAttribute('data-block-json') || '{}');
        this.editingBlockId = blockData.id;

        const modal = document.getElementById('inlineBlockModal');
        modal.style.display = 'flex';
        this.showBlockForm(blockData.type || 'richtext', blockData);
    },

    showBlockForm(type, existingData) {
        const titleEl = document.getElementById('inlineModalTitle');
        const body = document.getElementById('inlineModalBody');
        const catalog = window.OLLI_BLOCK_CATALOG || {};
        const info = catalog[type] || { name: type, icon: '📦' };

        const data = existingData || this.getDefaultBlockData(type);
        titleEl.textContent = (existingData ? 'Muokkaa lohkoa: ' : 'Uusi lohko: ') + info.icon + ' ' + info.name;

        let fieldsHtml = `
            <form id="inlineBlockEditForm" onsubmit="return OlliInlineEditor.submitBlockForm(event, '${type}')" style="display:flex;flex-direction:column;gap:0.9rem;">
                <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:0.75rem;">
                    <label style="font-size:0.85rem;font-weight:600;">Lohkon otsikko
                        <input type="text" name="title" value="${this.escapeAttr(data.title || '')}">
                    </label>
                    <label style="font-size:0.85rem;font-weight:600;">Tunniste (Badge)
                        <input type="text" name="badge" value="${this.escapeAttr(data.badge || '')}" placeholder="esim. Tärkeä">
                    </label>
                    <label style="font-size:0.85rem;font-weight:600;">Kortin väriteema
                        <select name="style">
                            <option value="surface" ${data.style === 'surface' ? 'selected' : ''}>Valkoinen / Peruskortti</option>
                            <option value="primary" ${data.style === 'primary' ? 'selected' : ''}>Päävärikorostus</option>
                            <option value="accent" ${data.style === 'accent' ? 'selected' : ''}>Aksenttikorostus</option>
                            <option value="gradient" ${data.style === 'gradient' ? 'selected' : ''}>Liukuväri (Hero)</option>
                            <option value="dark" ${data.style === 'dark' ? 'selected' : ''}>Tumma kortti</option>
                        </select>
                    </label>
                </div>
        `;

        if (type === 'richtext' || type === 'hero' || type === 'callout' || type === 'code') {
            fieldsHtml += `
                <label style="font-size:0.85rem;font-weight:600;">Sisältö (tukee tekstin lihavointia **teksti** tai HTML:ää)
                    <textarea name="content" rows="6">${this.escapeHtml(data.content || '')}</textarea>
                </label>`;
        }

        if (type === 'callout') {
            fieldsHtml += `
                <div style="display:grid;grid-template-columns:100px 1fr 1fr;gap:0.75rem;">
                    <label style="font-size:0.85rem;font-weight:600;">Ikoni
                        <input type="text" name="icon" value="${this.escapeAttr(data.icon || '💡')}">
                    </label>
                    <label style="font-size:0.85rem;font-weight:600;">Painikkeen teksti
                        <input type="text" name="button_text" value="${this.escapeAttr(data.button_text || '')}">
                    </label>
                    <label style="font-size:0.85rem;font-weight:600;">Painikkeen URL
                        <input type="text" name="button_url" value="${this.escapeAttr(data.button_url || '')}">
                    </label>
                </div>`;
        }

        if (type === 'hero') {
            fieldsHtml += `
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
                    <label style="font-size:0.85rem;font-weight:600;">Ensisijainen painike
                        <input type="text" name="button_text" value="${this.escapeAttr(data.button_text || '')}">
                    </label>
                    <label style="font-size:0.85rem;font-weight:600;">Painikkeen osoite (URL)
                        <input type="text" name="button_url" value="${this.escapeAttr(data.button_url || '')}">
                    </label>
                </div>`;
        }

        if (type === 'post_feed') {
            fieldsHtml += `
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0.75rem;">
                    <label style="font-size:0.85rem;font-weight:600;">Kategoria (all = kaikki)
                        <input type="text" name="category" value="${this.escapeAttr(data.category || 'all')}">
                    </label>
                    <label style="font-size:0.85rem;font-weight:600;">Lukumäärä
                        <input type="number" name="limit" value="${Number(data.limit || 3)}" min="1" max="12">
                    </label>
                    <label style="font-size:0.85rem;font-weight:600;">Esitystapa
                        <select name="layout_mode">
                            <option value="cards" ${data.layout_mode === 'cards' ? 'selected' : ''}>Kortit</option>
                            <option value="compact" ${data.layout_mode === 'compact' ? 'selected' : ''}>Kompakti lista</option>
                        </select>
                    </label>
                </div>`;
        }

        if (type === 'embed') {
            fieldsHtml += `
                <label style="font-size:0.85rem;font-weight:600;">Videon tai upotteen URL (esim. YouTube-linkki)
                    <input type="text" name="embed_url" value="${this.escapeAttr(data.embed_url || '')}">
                </label>
                <label style="font-size:0.85rem;font-weight:600;">Kuvateksti
                    <input type="text" name="caption" value="${this.escapeAttr(data.caption || '')}">
                </label>`;
        }

        if (['accordion', 'links', 'files', 'gallery', 'stats'].includes(type)) {
            const itemsJson = JSON.stringify(data.items || [], null, 2);
            fieldsHtml += `
                <label style="font-size:0.85rem;font-weight:600;">Listan alkiot (JSON-muodossa, tai muokkaa visuaalisesti Hallintapaneelissa)
                    <textarea name="items_json" rows="7" style="font-family:monospace;font-size:0.82rem;">${this.escapeHtml(itemsJson)}</textarea>
                </label>`;
        }

        fieldsHtml += `
                <div style="display:flex;justify-content:flex-end;gap:0.75rem;margin-top:0.5rem;">
                    <button type="button" class="btn btn-outline" onclick="OlliInlineEditor.closeModal()">Peruuta</button>
                    <button type="submit" class="btn btn-primary">💾 Tallenna lohko</button>
                </div>
            </form>`;

        body.innerHTML = fieldsHtml;
    },

    getDefaultBlockData(type) {
        const id = 'blk-' + Math.random().toString(36).substring(2, 8);
        switch (type) {
            case 'callout':
                return { id, type, title: 'Huomioitavaa', badge: 'Info', style: 'primary', icon: '💡', content: 'Kirjoita tähän tärkeä huomio tai ohje.' };
            case 'accordion':
                return { id, type, title: 'Kurssimoduulit / UKK', style: 'surface', items: [{ question: 'Ensimmäinen osio', answer: 'Osion sisältöteksti tulee tähän.' }] };
            case 'links':
                return { id, type, title: 'Pikalinkit', style: 'surface', items: [{ icon: '🔗', label: 'Esimerkkilinkki', url: '#' }] };
            case 'files':
                return { id, type, title: 'Ladattavat tiedostot', style: 'surface', items: [{ name: 'Liitetiedosto.pdf', size: '500 Kt', url: '#' }] };
            case 'stats':
                return { id, type, title: 'Avainluvut', style: 'surface', items: [{ value: '100 %', label: 'Valmiusaste' }, { value: '24/7', label: 'Saatavuus' }] };
            case 'gallery':
                return { id, type, title: 'Kuvagalleria', style: 'surface', columns: 2, items: [{ url: 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?auto=format&fit=crop&w=800&q=80', caption: 'Esimerkkikuva' }] };
            case 'post_feed':
                return { id, type, title: 'Uusimmat artikkelit', style: 'surface', category: 'all', limit: 3, layout_mode: 'cards' };
            case 'contact_form':
                return { id, type, title: 'Ota yhteyttä', style: 'surface', description: 'Lähetä meille viesti alla olevalla lomakkeella.', submit_text: 'Lähetä viesti' };
            default:
                return { id, type, title: 'Uusi sisältölohko', style: 'surface', content: '<p>Kirjoita sisältösi tähän...</p>' };
        }
    },

    async submitBlockForm(event, type) {
        event.preventDefault();
        this.syncDomToPageData();
        const form = event.target;
        const fd = new FormData(form);

        const blockObj = {
            id: this.editingBlockId || ('blk-' + Math.random().toString(36).substring(2, 8)),
            type: type,
            title: fd.get('title') || '',
            badge: fd.get('badge') || '',
            style: fd.get('style') || 'surface',
        };

        for (const [k, v] of fd.entries()) {
            if (k === 'items_json') {
                try {
                    blockObj.items = JSON.parse(v);
                } catch (e) {
                    alert('Virheellinen JSON-muoto listan alkioissa.');
                    return false;
                }
            } else {
                blockObj[k] = v;
            }
        }

        const colArr = window.OLLI_PAGE_DATA.columns[this.targetColumn] || [];
        if (this.editingBlockId) {
            const idx = colArr.findIndex(b => b.id === this.editingBlockId);
            if (idx !== -1) colArr[idx] = blockObj;
        } else {
            colArr.push(blockObj);
        }
        window.OLLI_PAGE_DATA.columns[this.targetColumn] = colArr;

        await this.savePageStructure(true);
        return false;
    },

    async deleteBlock(btnEl) {
        if (!confirm('Haluatko varmasti poistaa tämän sisältölohkon?')) return;
        const blockEl = btnEl.closest('.bento-block');
        if (blockEl) {
            blockEl.remove();
            await this.savePageStructure(false);
        }
    },

    closeModal() {
        const modal = document.getElementById('inlineBlockModal');
        if (modal) modal.style.display = 'none';
    },

    escapeHtml(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    },

    escapeAttr(str) {
        return this.escapeHtml(str).replace(/"/g, '&quot;');
    }
};

document.addEventListener('DOMContentLoaded', () => {
    OlliInlineEditor.init();
});
