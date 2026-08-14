document.addEventListener("DOMContentLoaded", function () {
            const hero = document.querySelector(".hero");
            const light = document.querySelector(".mouse-light");

            let mouseX = -500;
            let mouseY = -500;

            let lightX = -500;
            let lightY = -500;

            hero.addEventListener("mousemove", function (event) {
                const rect = hero.getBoundingClientRect();

                mouseX = event.clientX - rect.left;
                mouseY = event.clientY - rect.top;
            });

            hero.addEventListener("mouseleave", function () {
                mouseX = -500;
                mouseY = -500;
            });

            function animateLight() {
                lightX += (mouseX - lightX) * 0.08;
                lightY += (mouseY - lightY) * 0.08;

                light.style.left = lightX + "px";
                light.style.top = lightY + "px";

                requestAnimationFrame(animateLight);
            }

            animateLight();
        });