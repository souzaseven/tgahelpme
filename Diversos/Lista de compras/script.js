document.addEventListener('DOMContentLoaded', loadList);

let itemCount = 0; // Contador de sequência de produtos
let totalQuantity = 0; // Quantidade total de produtos
let totalPrice = 0; // Valor total dos produtos

function addItem() {
    const itemInput = document.getElementById('item-input');
    const itemText = itemInput.value.trim();
    if (itemText === '') return;

    let quantity = 1;
    if (confirm('Deseja adicionar uma quantidade para o item?')) {
        quantity = parseInt(prompt('Digite a quantidade:', '1'), 10);
        if (isNaN(quantity) || quantity <= 0) {
            alert('Quantidade inválida, será considerada 1.');
            quantity = 1;
        }
    }

    let unitPrice = 0;
    if (confirm('Deseja adicionar um preço por unidade para o item?')) {
        unitPrice = parseFloat(prompt('Digite o preço por unidade (em R$):', '0').replace(',', '.'));
        if (isNaN(unitPrice) || unitPrice < 0) {
            alert('Preço inválido, será considerado R$ 0,00.');
            unitPrice = 0;
        }
    }

    const totalItemPrice = quantity * unitPrice;
    itemCount++;
    totalQuantity += quantity;
    totalPrice += totalItemPrice;

    const list = document.getElementById('shopping-list');
    const listItem = document.createElement('li');
    listItem.className = 'list-item';

    // Divs para cada informação
    const sequenceDiv = document.createElement('div');
    sequenceDiv.textContent = `${itemCount}°`;

    const nameDiv = document.createElement('div');
    nameDiv.textContent = itemText;

    const quantityDiv = document.createElement('div');
    quantityDiv.textContent = quantity;

    const unitPriceDiv = document.createElement('div');
    unitPriceDiv.textContent = `R$ ${unitPrice.toFixed(2)}`;

    const totalDiv = document.createElement('div');
    totalDiv.textContent = `R$ ${totalItemPrice.toFixed(2)}`;

    // Ações (ícones de confirmar, editar e remover)
    const actionsDiv = document.createElement('div');

    const confirmIcon = document.createElement('span');
    confirmIcon.className = 'confirm-icon';
    confirmIcon.textContent = '✅';
    confirmIcon.setAttribute('data-tooltip', 'Confirmar'); // Adiciona a tooltip
    confirmIcon.addEventListener('click', () => {
        listItem.classList.toggle('confirmed');
        saveList();
    });
    actionsDiv.appendChild(confirmIcon);

    const editIcon = document.createElement('span');
    editIcon.className = 'edit-icon';
    editIcon.textContent = '✏️';
    editIcon.setAttribute('data-tooltip', 'Editar'); // Adiciona a tooltip
    editIcon.addEventListener('click', () => {
        editItem(listItem, itemText, quantity, unitPrice);
    });
    actionsDiv.appendChild(editIcon);

    const removeIcon = document.createElement('span');
    removeIcon.className = 'remove-icon';
    removeIcon.textContent = '❌';
    removeIcon.setAttribute('data-tooltip', 'Excluir'); // Adiciona a tooltip
    removeIcon.addEventListener('click', () => {
        listItem.remove();
        itemCount--;
        totalQuantity -= quantity;
        totalPrice -= totalItemPrice;
        saveList();
        updateSummary();
    });
    actionsDiv.appendChild(removeIcon);

    // Adiciona todos os elementos à linha do item
    listItem.appendChild(sequenceDiv);
    listItem.appendChild(nameDiv);
    listItem.appendChild(quantityDiv);
    listItem.appendChild(unitPriceDiv);
    listItem.appendChild(totalDiv);
    listItem.appendChild(actionsDiv);

    list.appendChild(listItem);
    itemInput.value = '';
    saveList();
    updateSummary();
}

function editItem(listItem, oldName, oldQuantity, oldUnitPrice) {
    const newName = prompt('Editar nome do produto:', oldName);
    const newQuantity = parseInt(prompt('Editar quantidade do produto:', oldQuantity), 10);
    const newUnitPrice = parseFloat(prompt('Editar preço por unidade (em R$):', oldUnitPrice).replace(',', '.'));

    if (!isNaN(newQuantity) && newQuantity > 0 && !isNaN(newUnitPrice) && newUnitPrice >= 0) {
        const totalItemPrice = newQuantity * newUnitPrice;

        listItem.querySelector('div:nth-child(2)').textContent = newName;
        listItem.querySelector('div:nth-child(3)').textContent = newQuantity;
        listItem.querySelector('div:nth-child(4)').textContent = `R$ ${newUnitPrice.toFixed(2)}`;
        listItem.querySelector('div:nth-child(5)').textContent = `R$ ${totalItemPrice.toFixed(2)}`;

        totalQuantity = totalQuantity - oldQuantity + newQuantity;
        totalPrice = totalPrice - (oldQuantity * oldUnitPrice) + totalItemPrice;

        saveList();
        updateSummary();
    } else {
        alert('Valores inválidos. Nenhuma alteração foi feita.');
    }
}

function clearAll() {
    const list = document.getElementById('shopping-list');
    list.innerHTML = '';
    itemCount = 0;
    totalQuantity = 0;
    totalPrice = 0;
    localStorage.removeItem('shopping-list');
    updateSummary();
}

function saveList() {
    const items = Array.from(document.querySelectorAll('.list-item')).map(item => ({
        sequence: item.querySelector('div:nth-child(1)').textContent,
        name: item.querySelector('div:nth-child(2)').textContent,
        quantity: item.querySelector('div:nth-child(3)').textContent,
        unitPrice: item.querySelector('div:nth-child(4)').textContent,
        total: item.querySelector('div:nth-child(5)').textContent,
        confirmed: item.classList.contains('confirmed')
    }));
    localStorage.setItem('shopping-list', JSON.stringify(items));
}

function loadList() {
    const savedItems = JSON.parse(localStorage.getItem('shopping-list') || '[]');
    const list = document.getElementById('shopping-list');
    savedItems.forEach(item => {
        const listItem = document.createElement('li');
        listItem.className = 'list-item';

        const sequenceDiv = document.createElement('div');
        sequenceDiv.textContent = item.sequence;

        const nameDiv = document.createElement('div');
        nameDiv.textContent = item.name;

        const quantityDiv = document.createElement('div');
        quantityDiv.textContent = item.quantity;

        const unitPriceDiv = document.createElement('div');
        unitPriceDiv.textContent = item.unitPrice;

        const totalDiv = document.createElement('div');
        totalDiv.textContent = item.total;

        const actionsDiv = document.createElement('div');

        const confirmIcon = document.createElement('span');
        confirmIcon.className = 'confirm-icon';
        confirmIcon.textContent = '✅';
        confirmIcon.setAttribute('data-tooltip', 'Confirmar');
        confirmIcon.addEventListener('click', () => {
            listItem.classList.toggle('confirmed');
            saveList();
        });
        actionsDiv.appendChild(confirmIcon);

        const editIcon = document.createElement('span');
        editIcon.className = 'edit-icon';
        editIcon.textContent = '✏️';
        editIcon.setAttribute('data-tooltip', 'Editar');
        editIcon.addEventListener('click', () => {
            editItem(listItem, item.name, parseInt(item.quantity), parseFloat(item.unitPrice.replace('R$', '').trim()));
        });
        actionsDiv.appendChild(editIcon);

        const removeIcon = document.createElement('span');
        removeIcon.className = 'remove-icon';
        removeIcon.textContent = '❌';
        removeIcon.setAttribute('data-tooltip', 'Excluir');
        removeIcon.addEventListener('click', () => {
            listItem.remove();
            itemCount--;
            totalQuantity -= parseInt(item.quantity);
            totalPrice -= parseFloat(item.total.replace('R$', '').trim());
            saveList();
            updateSummary();
        });
        actionsDiv.appendChild(removeIcon);

        listItem.appendChild(sequenceDiv);
        listItem.appendChild(nameDiv);
        listItem.appendChild(quantityDiv);
        listItem.appendChild(unitPriceDiv);
        listItem.appendChild(totalDiv);
        listItem.appendChild(actionsDiv);

        list.appendChild(listItem);
        itemCount = parseInt(item.sequence.replace('°', ''));
        totalQuantity += parseInt(item.quantity);
        totalPrice += parseFloat(item.total.replace('R$', '').trim());
    });
    updateSummary();
}

function updateSummary() {
    document.getElementById('total-sequence').textContent = `Produtos na lista: ${itemCount}`;
    document.getElementById('total-quantity').textContent = `Total de produtos: ${totalQuantity}`;
    document.getElementById('total-price').textContent = `Valor total: R$ ${totalPrice.toFixed(2)}`;
}
