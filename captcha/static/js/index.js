window.onload = function () {
    const wait = document.querySelector("#wait");
    const genBtn = document.querySelector("#gen");
    const successBtn = document.querySelector("#success");
    const resultBox = document.querySelector("#result");
    const resultBtn = document.querySelector("#result-btn");
    const toastBox = document.querySelector(".toast-box");
    const manualMode = document.querySelector("#manual-mode");
    const assistMode = document.querySelector("#assist-mode");
    const codeBox = document.querySelector("#code-box");
    const codeSubmitBtn = document.querySelector("#code-submit-btn");
    const smsCodeInput = document.querySelector("#sms-code");
    const flowType = document.querySelector("#flow-type");
    const flowTitle = document.querySelector("#flow-title");
    const flowMessage = document.querySelector("#flow-message");
    const maskedPhoneBox = document.querySelector("#masked-phone-box");
    const maskedPhone = document.querySelector("#masked-phone");

    const gtInput = document.querySelector("#gt");
    const challengeInput = document.querySelector("#challenge");
    const newChallengeInput = document.querySelector("#new-challenge");
    const validateInput = document.querySelector("#validate");
    const seccodeInput = document.querySelector("#seccode");
    const params = new URLSearchParams(location.search);
    const flowId = params.get("id") || "";
    const legacyGt = params.get("gt") || "";
    const legacyChallenge = params.get("challenge") || "";
    const isAssistMode = flowId !== "";
    let isSubmitting = false;
    let isPolling = false;
    let geetestInitialized = false;

    class GeeTest {
        constructor(gt, challenge) {
            this.gt = gt;
            this.challenge = challenge;
        }

        init(now = false) {
            initGeetest({
                gt: this.gt,
                challenge: this.challenge,
                offline: false,
                new_captcha: true,
                product: now ? "bind" : "popup",
                width: "100%",
            }, function (captchaObj) {
                if (now) {
                    setTimeout(() => {
                        hide(wait);
                        captchaObj.verify();
                    }, Math.floor(Math.random() * 2000) + 1000);
                } else {
                    captchaObj.appendTo("#captcha");
                }

                captchaObj.onReady(() => {
                    if (!now) hide(wait);
                }).onSuccess(() => {
                    showToastBox("验证成功");
                    if (now) {
                        hide(wait);
                        show(successBtn);
                    }
                    const result = captchaObj.getValidate();
                    newChallengeInput.value = result.geetest_challenge;
                    validateInput.value = result.geetest_validate;
                    seccodeInput.value = result.geetest_seccode;
                    show(resultBox);
                }).onError(err => {
                    showToastBox("验证失败 " + err.msg, 3000);
                    if (now) {
                        hide(wait);
                        show(genBtn);
                    }
                });
            });
        }
    }

    if (isAssistMode) {
        show(assistMode);
        hide(resultBox);
        hide(codeBox);
        hide(genBtn);
        show(wait);
        pollManualFlow();
    } else {
        show(manualMode);
        if (legacyGt !== "" && legacyChallenge !== "") {
            gtInput.value = legacyGt;
            challengeInput.value = legacyChallenge;
            hide(genBtn);
            show(wait);
            new GeeTest(legacyGt, legacyChallenge).init();
        }
    }

    genBtn.onclick = () => {
        const gt = gtInput.value;
        const challenge = challengeInput.value;
        if (gt === "" || challenge === "") {
            showToastBox("gt 和 challenge 不能为空", 3000);
            return;
        }
        if (gt.length !== 32 || challenge.length !== 32) {
            showToastBox("gt 或 challenge 长度错误", 3000);
            return;
        }

        hide(genBtn);
        show(wait);
        new GeeTest(gt, challenge).init(true);
    };

    resultBtn.onclick = () => {
        if (isSubmitting) {
            return;
        }

        if (challengeInput.value === "" || newChallengeInput.value === "" || validateInput.value === "" || seccodeInput.value === "") {
            showToastBox("请先完成验证码并生成结果", 3000);
            return;
        }

        const path = isAssistMode ? "/api/manual-flow/geetest" : "feedback";
        const payload = {
            challenge: challengeInput.value,
            new_challenge: newChallengeInput.value,
            validate: validateInput.value,
            seccode: seccodeInput.value
        };
        if (isAssistMode) {
            payload.id = flowId;
        }

        isSubmitting = true;
        setButtonBusy(resultBtn, true, "提交中...");

        fetch(path, {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
            },
            body: new URLSearchParams(payload)
        }).then(async response => {
            const data = await response.json().catch(() => ({}));
            return {ok: response.ok, data};
        }).then(({ok, data}) => {
            if (!ok) {
                showToastBox(data.message || "提交失败");
                return;
            }

            if (data.code === 10003 || data.code === 10040) {
                showToastBox("提交成功");
                setButtonBusy(resultBtn, true, "提交成功");
                show(successBtn);
                hide(genBtn);
                return;
            }

            showToastBox(data.message || "提交失败");
        }).catch(err => {
            showToastBox("error: " + err.message);
        }).finally(() => {
            if (resultBtn.textContent !== "提交成功") {
                isSubmitting = false;
                setButtonBusy(resultBtn, false, "提交验证结果");
            }
        });
    };

    codeSubmitBtn.onclick = () => {
        if (isSubmitting) {
            return;
        }

        const code = smsCodeInput.value.trim();
        if (code === "") {
            showToastBox("请输入短信验证码", 3000);
            return;
        }

        isSubmitting = true;
        setButtonBusy(codeSubmitBtn, true, "提交中...");
        fetch("/api/manual-flow/code", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
            },
            body: new URLSearchParams({
                id: flowId,
                code: code
            })
        }).then(async response => {
            const data = await response.json().catch(() => ({}));
            return {ok: response.ok, data};
        }).then(({ok, data}) => {
            if (!ok) {
                showToastBox(data.message || "提交失败");
                return;
            }

            if (data.code === 10030) {
                showToastBox("短信验证码提交成功");
                setButtonBusy(codeSubmitBtn, true, "提交成功");
                show(successBtn);
                hide(genBtn);
                return;
            }

            showToastBox(data.message || "提交失败");
        }).catch(err => {
            showToastBox("error: " + err.message);
        }).finally(() => {
            if (codeSubmitBtn.textContent !== "提交成功") {
                isSubmitting = false;
                setButtonBusy(codeSubmitBtn, false, "提交验证码");
            }
        });
    };

    function pollManualFlow() {
        if (isPolling) {
            return;
        }
        isPolling = true;

        const fetchOnce = () => {
            fetch("/api/manual-flow?id=" + encodeURIComponent(flowId))
                .then(async response => {
                    const data = await response.json().catch(() => ({}));
                    return {ok: response.ok, data};
                })
                .then(({ok, data}) => {
                    if (!ok || data.code !== 10020 || !data.data) {
                        showToastBox(data.message || "加载登录助手状态失败", 3000);
                        hide(wait);
                        show(genBtn);
                        genBtn.textContent = "刷新页面";
                        return;
                    }

                    renderAssistState(data.data);
                    if (data.data.status === "submitted" || data.data.status === "resolved") {
                        setTimeout(fetchOnce, 2000);
                        return;
                    }

                    setTimeout(fetchOnce, 3000);
                })
                .catch(err => {
                    showToastBox("error: " + err.message, 3000);
                    hide(wait);
                    show(genBtn);
                    genBtn.textContent = "刷新页面";
                });
        };

        fetchOnce();
    }

    function renderAssistState(flow) {
        flowType.textContent = humanType(flow.type);
        flowTitle.textContent = flow.title || "登录助手";
        flowMessage.textContent = flow.message || "请按当前页面提示继续处理。";
        syncStatusAppearance(flow);
        challengeInput.value = flow.challenge || "";
        gtInput.value = flow.gt || "";
        if (flow.masked_phone) {
            maskedPhone.value = flow.masked_phone;
            show(maskedPhoneBox);
        } else {
            hide(maskedPhoneBox);
        }

        if (flow.type === "geetest") {
            hide(codeBox);
            if (validateInput.value !== "" && seccodeInput.value !== "") {
                hide(wait);
                show(resultBox);
                return;
            }

            show(genBtn);
            hide(wait);
            if (flow.gt && flow.challenge && !resultBox.classList.contains("hide")) {
                return;
            }
            if (flow.gt && flow.challenge && !geetestInitialized) {
                geetestInitialized = true;
                hide(genBtn);
                show(wait);
                new GeeTest(flow.gt, flow.challenge).init();
            }
            return;
        }

        hide(resultBox);
        show(codeBox);
        hide(wait);
        hide(genBtn);
        if (flow.status === "submitted") {
            setButtonBusy(codeSubmitBtn, true, "已提交，等待处理");
        }
        if (flow.status === "resolved") {
            setButtonBusy(codeSubmitBtn, true, "处理完成");
        }
        if (flow.status === "expired") {
            flowMessage.textContent = "当前登录助手流程已过期，请重新发起完整登录。";
            setButtonBusy(codeSubmitBtn, true, "已过期");
        }
    }

    function syncStatusAppearance(flow) {
        const statusCard = document.querySelector(".status-card");
        if (!statusCard) {
            return;
        }

        flowType.className = "status-label";
        statusCard.classList.remove("is-risk", "is-sms", "is-expired");

        if (flow.type === "geetest") {
            flowType.classList.add("type-geetest");
        } else if (flow.type === "sms_code") {
            flowType.classList.add("type-sms");
            statusCard.classList.add("is-sms");
        } else if (flow.type === "risk_sms_code") {
            flowType.classList.add("type-risk");
            statusCard.classList.add("is-risk");
        }

        if (flow.status === "expired") {
            flowType.classList.add("state-expired");
            statusCard.classList.add("is-expired");
        }
    }

    function humanType(type) {
        switch (type) {
            case "geetest":
                return "行为验证码";
            case "sms_code":
                return "短信验证码";
            case "risk_sms_code":
                return "风控短信验证码";
            default:
                return "等待处理";
        }
    }

    let timer = null;

    function showToastBox(text, timeout = 2000) {
        toastBox.textContent = text;
        toastBox.style.opacity = 1;
        toastBox.style.top = "50px";
        if (timer != null) clearTimeout(timer);
        timer = setTimeout(() => {
            toastBox.style.top = "-30px";
            toastBox.style.opacity = 0;
        }, timeout);
    }

    function hide(el) {
        el.classList.add("hide");
    }

    function show(el) {
        el.classList.remove("hide");
    }

    function setButtonBusy(el, busy, text) {
        el.textContent = text;
        el.classList.toggle("is-disabled", busy);
    }
};
