import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['collectionContainer'];

    static values = {
        index: Number,
        prototype: String,
    };

    connect() {
        this.collectionContainerTarget.querySelectorAll('[data-form-collection-item]').forEach((wrapper) => {
            this.addDeleteButton(wrapper);
        });
    }

    addCollectionElement(event) {
        if (event) {
            event.preventDefault();
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'mb-3';
        wrapper.setAttribute('data-form-collection-item', 'true');
        wrapper.innerHTML = this.prototypeValue.replace(/__name__/g, this.indexValue);

        this.collectionContainerTarget.appendChild(wrapper);

        this.indexValue++;
        this.addDeleteButton(wrapper);
    }

    addDeleteButton(wrapper) {
        if (wrapper.querySelector('[data-form-collection-delete]')) {
            return;
        }

        const removeFormButton = document.createElement('a');
        removeFormButton.href = '#';
        removeFormButton.className = 'btn btn-danger mt-2';
        removeFormButton.setAttribute('data-form-collection-delete', 'true');
        removeFormButton.textContent = 'Supprimer';

        const actionsContainer = wrapper.querySelector('[data-form-collection-actions]');
        if (actionsContainer) {
            actionsContainer.appendChild(removeFormButton);
        } else {
            wrapper.appendChild(removeFormButton);
        }

        removeFormButton.addEventListener('click', (e) => {
            e.preventDefault();
            wrapper.remove();
        });
    }
}
