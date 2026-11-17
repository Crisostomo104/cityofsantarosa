document.addEventListener('click', event => {
    if (!event.target.matches('.edit-chatbot-response')) return;

    event.preventDefault();
    const row = event.target.closest('.chatbot-response-row');
    if (!row) return;

    const id = row.dataset.responseId;
    const keywords = row.querySelector('.response-keywords').innerText.trim();
    const reply = row.querySelector('.response-reply').innerText.trim();

    const form = document.getElementById('chatbotResponseForm');
    if (!form) return;

    form.querySelector('[name="response_id"]').value = id;
    if (form.querySelector('[name="response_action"]')) {
        form.querySelector('[name="response_action"]').value = 'save';
    }
    form.querySelector('[name="keywords"]').value = keywords;
    form.querySelector('[name="reply"]').value = reply;
    form.querySelector('.chatbot-form-title').textContent = 'Edit Chatbot Response';
    form.querySelector('.chatbot-form-submit').textContent = 'Save Changes';
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
});

const resetChatbotForm = document.getElementById('resetChatbotForm');
if (resetChatbotForm) {
    resetChatbotForm.addEventListener('click', () => {
        const form = document.getElementById('chatbotResponseForm');
        if (!form) return;

        form.reset();
        form.querySelector('[name="response_id"]').value = '0';
        if (form.querySelector('[name="response_action"]')) {
            form.querySelector('[name="response_action"]').value = 'save';
        }
        form.querySelector('.chatbot-form-title').textContent = 'Add New Chatbot Response';
        form.querySelector('.chatbot-form-submit').textContent = 'Add Response';
    });
}
