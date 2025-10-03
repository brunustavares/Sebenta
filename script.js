/**
 * Sebenta
 * Moodle block for grades synchronization with WISEflow (teachers’ function)
 * and integrated grades and submission statements (students’ function).
 * (developed for UAb - Universidade Aberta)
 *
 * @category   Moodle_Block
 * @package    block_sebenta
 * @author     Bruno Tavares <brunustavares@gmail.com>
 * @link       https://www.linkedin.com/in/brunomastavares/
 * @copyright  Copyright (C) 2023-2025 Bruno Tavares
 * @license    GNU General Public License v3 or later
 *             https://www.gnu.org/licenses/gpl-3.0.html
 * @version    2025021305
 * @date       2023-03-21
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

// funções para alterar a data de fim da avaliação
function setFlowInfo(value, flow_info) {
    document.getElementById("btnEndAssessDate").value = value;
    document.getElementById("flow_info").textContent = flow_info;
}

function endflowmarking(flowid, auth_chain, wf_url) {
    var http = new XMLHttpRequest();
    var ws_url = "../blocks/sebenta/wf_endpoints.php";
    var params = "action=endflowmarking&flowid=" + flowid + "&auth_chain=" + auth_chain + "&url=" + wf_url;

    http.open("POST", ws_url, true);
    http.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    http.onreadystatechange = function() {
        if(http.readyState == 4 && http.status == 200) {
            // console.log(http.responseText);
        }
    }
    http.send(params);

    // window.alert("Operação executada, esta página será recarregada!");
    document.getElementById("confirmation").innerHTML = "<h4><b>Avaliação finalizada!</b><br><br>Esta página está a ser actualizada...</h4>";
    document.getElementById("buttons").style.display = "none";

    self.location.reload();
}

// certificados: funções para navegação no carrossel de cartões
document.addEventListener("DOMContentLoaded", () => {
    const carousel = document.querySelector(".sebenta_carousel");
    const prev = document.getElementById("sebenta_prev");
    const next = document.getElementById("sebenta_next");

    if (carousel) {
        // popula array de cartões e define índice inicial
        let items = Array.from(document.querySelectorAll(".sebenta_carousel-item"));
        let currentIndex = 1;

        // if (items.length > currentIndex) {
            // replica primeiro e último cartões, para acrescentar nos extremos e simular a rotação infinita
            const firstClone = items[0].cloneNode(true);
            const lastClone = items[items.length - 1].cloneNode(true);

            carousel.appendChild(firstClone);
            carousel.insertBefore(lastClone, items[0]);

            // actualiza array de cartões, após adição dos clones
            items = Array.from(document.querySelectorAll(".sebenta_carousel-item"));

            // gere a animação do carrossel
            const updateCarousel = (animate = true) => {
                carousel.style.transition = animate
                    ? "transform 0.3s ease-in-out"
                    : "none";
                carousel.style.transform = `translateX(-${currentIndex * 100}%)`;
                items.forEach((item, index) => {
                    item.classList.toggle("active", index === currentIndex);
                });
            };

            // actualiza o índice do cartão activo e gere a navegação nos extremos do carrossel
            const handleEdgeCases = () => {
                if (currentIndex === 0) {
                    currentIndex = items.length - 2;
                } else if (currentIndex === items.length - 1) {
                    currentIndex = 1;
                }
                updateCarousel(false);
            };

            prev.addEventListener("click", () => {
                currentIndex--;
                updateCarousel();
                setTimeout(handleEdgeCases, 300);
            });

            next.addEventListener("click", () => {
                currentIndex++;
                updateCarousel();
                setTimeout(handleEdgeCases, 300);
            });
        
        // } else {
        //     items[0].classList.toggle("active");
            
        // }

        updateCarousel(false); // configuração inicial

    } else { // TODO: função para carregamento progressivo de fluxos
        let start = 0;
        const loadMoreBtn = document.getElementById("load-more-btn");
        const flowsContainer = document.getElementById("wiseflow");

        function fetchFlows(flows_array, limit) {
            fetch(`./fetch_flows.php?flows_array=${flows_array}&start=${start}`)
                .then(response => response.json())
                .then(data => {
                    if (data.length === 0) {
                        loadMoreBtn.style.display = "none"; // ocultar botão, quando não houver mais registos

                    } else {
                        data.forEach(record => {
                            const recordDiv = document.createElement("div");
                            recordDiv.classList.add("record");
                            recordDiv.innerHTML = `
                                <h3>${record.name}</h3>
                                <p>${record.description}</p>
                            `;
                            flowsContainer.appendChild(recordDiv);
                        });

                        start += limit; // incrementa o índice de início, para carregamento adicional

                    }
                })
                
                .catch(error => console.error("Error fetching data:", error));
        }

    // carregamento inicial
    fetchFlows();

    // carregamento adicional
    loadMoreBtn.addEventListener("click", fetchFlows);

    }

});
