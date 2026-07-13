(function() {
    'use strict';

    function questionSlot(element) {
        var match = element.id.match(/question-\d+-(\d+)/);
        return match ? Number(match[1]) : 0;
    }

    function graded(element) {
        return element.classList.contains('gradedright') || element.classList.contains('gradedwrong') ||
            element.classList.contains('gradedpartial') || Boolean(element.querySelector('.coderunner-test-results'));
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

        button.addEventListener('click', function() {
            if (button.disabled) {
                return;
            }
            button.disabled = true;
            button.textContent = config.strings.loading;
            status.textContent = '';
            var body = new URLSearchParams({
                attemptid: String(config.attemptId),
                slot: String(slot),
                sesskey: config.sesskey,
            });
            fetch(config.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
                body: body.toString(),
            }).then(function(response) {
                return response.json().then(function(data) {
                    if (!response.ok || !data.success) {
                        throw new Error(data.error || config.strings.error);
                    }
                    return data;
                });
            }).then(function(data) {
                status.textContent = '';
                result.innerHTML = data.html;
                button.textContent = config.strings.showAgain;
                button.disabled = false;
            }).catch(function(error) {
                status.textContent = error.message || config.strings.error;
                button.textContent = config.strings.button;
                button.disabled = false;
            });
        });
    }

    function init() {
        var config = window.localAiCodeHelper;
        if (!config) {
            return;
        }
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
