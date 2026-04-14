window.onload = function () {
    const wait = document.querySelector("#wait")
    const genBtn = document.querySelector("#gen")
    const successBtn = document.querySelector("#success")
    const resultBox = document.querySelector("#result")
    const resultBtn = document.querySelector("#result-btn")
    const toastBox = document.querySelector(".toast-box")

    const gtInput = document.querySelector("#gt")
    const challengeInput = document.querySelector("#challenge")
    const newChallengeInput = document.querySelector("#new-challenge")
    const validateInput = document.querySelector("#validate")
    const seccodeInput = document.querySelector("#seccode")
    let isSubmitting = false

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
                if (now) setTimeout(() => {
                    hide(wait);
                    captchaObj.verify();
                }, Math.floor(Math.random() * 2000) + 1000);
                else captchaObj.appendTo("#captcha");

                captchaObj.onReady(() => {
                    if (!now) hide(wait);
                }).onSuccess(() => {
                    console.log("验证成功");
                    showToastBox("验证成功");
                    if (now) {
                        hide(wait);
                        show(successBtn);
                    }
                    const result = captchaObj.getValidate();
                    console.log(result)
                    newChallengeInput.value = result.geetest_challenge;
                    validateInput.value = result.geetest_validate;
                    seccodeInput.value = result.geetest_seccode;

                    show(resultBox)
                }).onError(err => {
                    console.log("验证失败");
                    console.log(err);
                    showToastBox("验证失败 " + err.msg, 3000);
                    if (now) {
                        hide(wait);
                        show(genBtn);
                    }
                });
            });
        }
    }

    genBtn.onclick = () => {
        let gt = gtInput.value;
        let challenge = challengeInput.value;
        if (gt === undefined || gt === '' || challenge === undefined || challenge === '') {
            console.log("gt 和 challenge 不能为空");
            showToastBox("gt 和 challenge 不能为空", 3000);
            return;
        }
        if (gt.length !== 32 || challenge.length !== 32) {
            console.log("gt 或 challenge 长度错误");
            showToastBox("gt 或 challenge 长度错误", 3000);
            return;
        }

        hide(genBtn);
        show(wait);

        new GeeTest(gt, challenge).init(true);
    }

    if (location.search !== '') {
        hide(genBtn);
        show(wait);

        const params = new URLSearchParams(location.search);
        const gt = params.get("gt") || '';
        const challenge = params.get("challenge") || '';
        if (gt !== '' && challenge !== '') {
            gtInput.value = gt;
            challengeInput.value = challenge;
            new GeeTest(gt, challenge).init();
        } else {
            console.log("未从URL中找到 gt 与 challenge");
            hide(wait);
            show(genBtn);
        }
    }

    resultBtn.onclick = () => {
        if (isSubmitting) {
            return;
        }

        if (challengeInput.value === '' || newChallengeInput.value === '' || validateInput.value === '' || seccodeInput.value === '') {
            showToastBox("请先完成验证码并生成结果", 3000);
            return;
        }

        isSubmitting = true;
        setButtonBusy(resultBtn, true, "提交中...");

        fetch('feedback', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: new URLSearchParams({
                challenge: challengeInput.value,
                new_challenge: newChallengeInput.value,
                validate: validateInput.value,
                seccode: seccodeInput.value
            })
        }).then(async response => {
            const data = await response.json().catch(() => ({}));
            return {ok: response.ok, data};
        }).then(({ok, data}) => {
            if (!ok) {
                showToastBox(data.message || "提交反馈失败");
                return;
            }

            if (data.code === 10003) {
                showToastBox("提交反馈成功");
                setButtonBusy(resultBtn, true, "提交成功");
                return;
            }

            showToastBox(data.message || "提交反馈失败");
        }).catch(err => {
            showToastBox('error: ' + err.message);
        }).finally(() => {
            if (resultBtn.textContent !== "提交成功") {
                isSubmitting = false;
                setButtonBusy(resultBtn, false, "提交反馈结果");
            }
        });
    }

    let timer = null

    function showToastBox(text, timeout = 2000) {
        toastBox.textContent = text;
        toastBox.style.opacity = 1;
        toastBox.style.top = '50px';
        if (timer != null) clearTimeout(timer)
        timer = setTimeout(() => {
            toastBox.style.top = '-30px';
            toastBox.style.opacity = 0;
        }, timeout)
    }

    function hide(el) {
        el.classList.add("hide")
    }

    function show(el) {
        el.classList.remove("hide")
    }

    function setButtonBusy(el, busy, text) {
        el.textContent = text;
        el.classList.toggle("is-disabled", busy);
    }
}
