(function() {
    'use strict';

    // Moodle помещает номер вопроса (slot) в id контейнера.
    function questionSlot(element) {
        var match = element.id.match(/question-\d+-(\d+)/);
        return match ? Number(match[1]) : 0;
    }

    function graded(element) {
        return element.classList.contains('gradedright') || element.classList.contains('gradedwrong') ||
            element.classList.contains('gradedpartial') || Boolean(element.querySelector('.coderunner-test-results'));
    }

    async function requestAnalysis(config, slot) {
        // Запрос идёт в PHP-плагин Moodle. Внутренний адрес AI service браузеру не передаётся.
        var body = new URLSearchParams({
            attemptid: String(config.attemptId),
            slot: String(slot),
            sesskey: config.sesskey,
        });
        var response = await fetch(config.endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
            body: body.toString(),
        });
        var data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.error || config.strings.error);
        }
        return data;
    }

    function addButton(question, config) {
        if (question.dataset.aiCodeHelperReady === '1' || (config.showAfterGrading && !graded(question))) {
            return;
        }
        if (config.onlyFailed && question.classList.contains('gradedright')) {
            return;
        }
        var slot = questionSlot(question);
        if (!slot) {
            return;
        }
        question.dataset.aiCodeHelperReady = '1';
        var wrapper = document.createElement('div');
        wrapper.className = 'local-aicodehelper mt-3';
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-secondary';
        button.textContent = config.strings.button;
        var status = document.createElement('span');
        status.className = 'ms-2';
        status.setAttribute('aria-live', 'polite');
        var result = document.createElement('div');
        wrapper.append(button, status, result);
        var feedback = question.querySelector('.specificfeedback') || question;
        feedback.appendChild(wrapper);

        button.addEventListener('click', async function() {
            if (button.disabled) {
                return;
            }
            // disabled защищает от нескольких параллельных запросов одним нажатием.
            button.disabled = true;
            button.textContent = config.strings.loading;
            status.textContent = '';

            try {
                var data = await requestAnalysis(config, slot);
                status.textContent = '';
                result.innerHTML = data.html;
                button.textContent = config.strings.showAgain;
            } catch (error) {
                status.textContent = error.message || config.strings.error;
                button.textContent = config.strings.button;
            } finally {
                button.disabled = false;
            }
        });
    }

    function init() {
        var config = window.localAiCodeHelper;
        if (!config) {
            return;
        }
        // Кнопка добавляется только к контейнерам вопросов CodeRunner.
        document.querySelectorAll('.que.coderunner').forEach(function(question) {
            addButton(question, config);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
