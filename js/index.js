// Aguarda o carregamento completo do HTML antes de executar o código.
document.addEventListener("DOMContentLoaded", function () {
            // Seleciona a seção principal onde o efeito será exibido.
            const hero = document.querySelector(".hero");

            // Seleciona o elemento visual que representa a luz do mouse.
            const light = document.querySelector(".mouse-light");

            // Guarda a posição atual do mouse dentro da seção hero.
            let mouseX = -500;
            let mouseY = -500;

            // Guarda a posição atual da luz para criar um movimento suave.
            let lightX = -500;
            let lightY = -500;

            // Atualiza a posição do mouse sempre que ele se movimenta na seção hero.
            hero.addEventListener("mousemove", function (event) {
                // Obtém a posição e o tamanho da seção em relação à janela.
                const rect = hero.getBoundingClientRect();

                // Converte a posição do mouse para coordenadas internas da seção.
                mouseX = event.clientX - rect.left;
                mouseY = event.clientY - rect.top;
            });

            // Esconde a luz quando o mouse sai da seção hero.
            hero.addEventListener("mouseleave", function () {
                mouseX = -500;
                mouseY = -500;
            });

            // Atualiza continuamente a posição da luz.
            function animateLight() {
                // Aproxima a luz da posição do mouse de forma gradual.
                lightX += (mouseX - lightX) * 0.08;
                lightY += (mouseY - lightY) * 0.08;

                // Aplica a nova posição da luz no elemento HTML.
                light.style.left = lightX + "px";
                light.style.top = lightY + "px";

                // Solicita a próxima atualização da animação.
                requestAnimationFrame(animateLight);
            }

            // Inicia o loop da animação da luz.
            animateLight();
        });