document.addEventListener("DOMContentLoaded", function () {

    const etapas = document.querySelectorAll(".etapa");

    etapas[0].classList.add("ativa");

    etapas.forEach(function (etapa, index) {

        const input = etapa.querySelector("input");

        if (!input) return;

        if (input.type === "checkbox") {

            input.addEventListener("change", function () {

                if (input.checked && etapas[index + 1]) {
                    etapas[index + 1].classList.add("ativa");
                }

            });

            return;
        }

        input.addEventListener("input", function () {

            if (input.value.trim() !== "") {

                if (etapas[index + 1]) {
                    etapas[index + 1].classList.add("ativa");
                }

            }

        });

        input.addEventListener("change", function () {

            if (input.value.trim() !== "") {

                if (etapas[index + 1]) {
                    etapas[index + 1].classList.add("ativa");
                }

            }

        });

    });

});