(function() {
    function initChooser(container) {
        if (!container || container.dataset.initialized) return;
        container.dataset.initialized = '1';

        const chooser = document.getElementById('person-directory-admin-chooser');
        if (!chooser) return;

        const allPeopleData = JSON.parse(chooser.getAttribute('data-all-people') || '[]');

        const idsFieldId = container.getAttribute('data-ids-field');
        const labelsFieldId = container.getAttribute('data-labels-field');
        const idsInput = document.getElementById(idsFieldId);
        const labelsInput = document.getElementById(labelsFieldId);

        if (!idsInput || !labelsInput) return;

        // Initialize state from inputs
        let selectedPeople = [];
        const currentIds = (idsInput.value || '').split(',').filter(id => id);
        const currentLabels = (labelsInput.value || '').split('|');

        currentIds.forEach((id, index) => {
            const person = allPeopleData.find(p => String(p.id) === String(id));
            if (person) {
                selectedPeople.push({
                    id: person.id,
                    title: person.title,
                    label: currentLabels[index] || ''
                });
            }
        });

        function updateInputs() {
            idsInput.value = selectedPeople.map(p => p.id).join(',');
            labelsInput.value = selectedPeople.map(p => p.label).join('|');
            // Trigger change event for WordPress widget saving
            idsInput.dispatchEvent(new Event('change', { bubbles: true }));
            labelsInput.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function render() {
            container.innerHTML = '';
            container.style.display = 'block';

            // Selected Section
            const selectedTitle = document.createElement('h4');
            selectedTitle.textContent = 'Selected People (Drag to reorder)';
            selectedTitle.style.marginTop = '0';
            container.appendChild(selectedTitle);

            const selectedList = document.createElement('div');
            selectedList.className = 'selected-people-list';
            selectedList.style.display = 'flex';
            selectedList.style.flexWrap = 'wrap';
            selectedList.style.gap = '12px';
            selectedList.style.marginBottom = '20px';
            selectedList.style.minHeight = '50px';
            selectedList.style.padding = '10px';
            selectedList.style.border = '1px dashed #ccc';
            selectedList.style.borderRadius = '4px';
            selectedList.style.background = '#f9f9f9';
            container.appendChild(selectedList);

            selectedPeople.forEach((person, index) => {
                const card = createCard(person, true, index);
                selectedList.appendChild(card);
            });

            if (selectedPeople.length === 0) {
                const empty = document.createElement('p');
                empty.textContent = 'No people selected.';
                empty.style.color = '#888';
                empty.style.fontStyle = 'italic';
                selectedList.appendChild(empty);
            }

            // Available Section
            const availableTitle = document.createElement('h4');
            availableTitle.textContent = 'Available People';
            container.appendChild(availableTitle);

            const availableList = document.createElement('div');
            availableList.className = 'available-people-list';
            availableList.style.display = 'flex';
            availableList.style.flexWrap = 'wrap';
            availableList.style.gap = '12px';
            availableList.style.padding = '10px';
            availableList.style.border = '1px solid #eee';
            availableList.style.borderRadius = '4px';
            container.appendChild(availableList);

            allPeopleData.forEach(person => {
                const isSelected = selectedPeople.some(p => String(p.id) === String(person.id));
                if (!isSelected) {
                    const card = createCard(person, false);
                    availableList.appendChild(card);
                }
            });

            if (allPeopleData.length === 0) {
                const empty = document.createElement('p');
                empty.textContent = 'No people found in directory.';
                availableList.appendChild(empty);
            }
        }

        function createCard(person, isSelected, index) {
            const card = document.createElement('div');
            card.className = 'person-trump-card' + (isSelected ? ' is-selected' : '');
            card.style.width = '160px';
            card.style.border = '1px solid #ddd';
            card.style.borderRadius = '8px';
            card.style.padding = '8px';
            card.style.background = '#fff';
            card.style.boxShadow = '0 1px 2px rgba(0,0,0,.06)';
            card.style.position = 'relative';
            card.style.cursor = isSelected ? 'move' : 'default';

            if (isSelected) {
                card.draggable = true;
                card.dataset.index = index;
            }

            const title = document.createElement('div');
            title.className = 'person-trump-title';
            title.style.fontWeight = '600';
            title.style.fontSize = '13px';
            title.style.lineHeight = '1.3';
            title.style.marginBottom = '4px';
            title.textContent = person.title;
            card.appendChild(title);

            const roleDiv = document.createElement('div');
            roleDiv.className = 'person-trump-role';
            roleDiv.style.marginTop = '6px';

            const label = document.createElement('label');
            label.style.display = 'block';
            label.style.fontSize = '11px';
            label.style.color = '#555';
            label.style.marginBottom = '2px';
            label.textContent = 'Role/Position';
            roleDiv.appendChild(label);

            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'widefat';
            input.style.width = '100%';
            input.style.boxSizing = 'border-box';
            input.style.fontSize = '12px';
            input.style.padding = '4px 6px';
            input.value = person.label || '';
            input.placeholder = 'Role/Position';

            input.addEventListener('input', (e) => {
                if (isSelected) {
                    selectedPeople[index].label = e.target.value;
                    updateInputs();
                }
            });
            roleDiv.appendChild(input);
            card.appendChild(roleDiv);

            const actionBtn = document.createElement('button');
            actionBtn.type = 'button';
            actionBtn.style.marginTop = '8px';
            actionBtn.style.width = '100%';
            actionBtn.className = 'button button-small';
            actionBtn.textContent = isSelected ? 'Remove' : 'Add';
            actionBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                if (isSelected) {
                    selectedPeople.splice(index, 1);
                } else {
                    selectedPeople.push({
                        id: person.id,
                        title: person.title,
                        label: input.value
                    });
                }
                updateInputs();
                render();
            });
            card.appendChild(actionBtn);

            if (isSelected) {
                card.addEventListener('dragstart', handleDragStart);
                card.addEventListener('dragover', handleDragOver);
                card.addEventListener('drop', handleDrop);
                card.addEventListener('dragend', handleDragEnd);
            }

            return card;
        }

        let dragSrcEl = null;

        function handleDragStart(e) {
            this.style.opacity = '0.4';
            dragSrcEl = this;
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', this.dataset.index);
        }

        function handleDragOver(e) {
            if (e.preventDefault) {
                e.preventDefault();
            }
            e.dataTransfer.dropEffect = 'move';
            return false;
        }

        function handleDrop(e) {
            if (e.stopPropagation) {
                e.stopPropagation();
            }

            if (dragSrcEl !== this) {
                const fromIndex = parseInt(dragSrcEl.dataset.index);
                const toIndex = parseInt(this.dataset.index);

                const movedItem = selectedPeople.splice(fromIndex, 1)[0];
                selectedPeople.splice(toIndex, 0, movedItem);

                updateInputs();
                render();
            }
            return false;
        }

        function handleDragEnd() {
            this.style.opacity = '1';
        }

        render();
    }

    // Auto-init for existing and future containers
    function discoverAndInit() {
        document.querySelectorAll('.person-directory-admin-cards').forEach(initChooser);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', discoverAndInit);
    } else {
        discoverAndInit();
    }

    // Support for WordPress Widget AJAX refreshes
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType === 1) { // Element node
                    if (node.classList.contains('person-directory-admin-cards')) {
                        initChooser(node);
                    } else {
                        node.querySelectorAll('.person-directory-admin-cards').forEach(initChooser);
                    }
                }
            });
        });
    });

    observer.observe(document.body, { childList: true, subtree: true });
})();
