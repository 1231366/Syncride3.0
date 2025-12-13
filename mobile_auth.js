// mobile_auth.js - Versão 4.0: Super-Robusta para Cold Start

// ===============================================
// INICIALIZAÇÃO DE PLUGINS DO CAPACITOR
// ===============================================
// NOTA: Os plugins devem ser acedidos de forma assíncrona.
const { Storage, App } = Capacitor.Plugins;

// 1. FUNÇÃO PRINCIPAL: TENTA LOGIN AUTOMÁTICO
async function autoLogin() {
    try {
        // Esta verificação é crucial: só avança se estiver na página de login
        // ou no index para evitar loops infinitos nas páginas internas.
        if (!window.location.pathname.includes('index.php') && window.location.pathname !== '/') {
            return; 
        }

        const { value: authToken } = await Storage.get({ key: 'auth_token' });
        const { value: userRole } = await Storage.get({ key: 'user_role' });

        if (authToken && userRole) {
            console.log("Token encontrado. A fazer login automático...");

            // Monta o URL de redirecionamento
            let route = (userRole === '1') ? '/Includes/dist/pages/admin.php' : '/Includes/dist/pages/driver.php';
            
            // Redireciona a App
            window.location.href = route;
        }
    } catch (e) {
        console.error("ERRO CRÍTICO no autoLogin (Storage indisponível):", e);
        // O erro deve ser ignorado para permitir o login manual.
    }
}


// 2. OUVINTE DE ESTADO DA APP (Para "Hot Start" e Retomar)
// Adiciona o ouvinte para que o login automático corra sempre que a App é ativada.
if (window.Capacitor) {
    App.addListener('appStateChange', (state) => {
        if (state.isActive) {
            // Se a App for para o primeiro plano, tenta logo o login
            autoLogin();
        }
    });
}


// 3. ENTRADA IMEDIATA (CRUCIAL PARA COLD START)
// Chamamos o autoLogin assim que o script é lido. 
// No Capacitor, esta é a forma mais agressiva e fiável de correr a lógica de sessão.
autoLogin();


// ===============================================
// LÓGICA DE SUBMISSÃO DO FORMULÁRIO
// ===============================================

document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    
    if (loginForm) {
        loginForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            const email = loginForm.querySelector('input[name="email"]').value;
            const password = loginForm.querySelector('input[name="pass"]').value;
            const rememberMe = loginForm.querySelector('#ckb1').checked; 

            const button = document.getElementById('btn_login');
            button.disabled = true;
            button.textContent = 'A processar...';

            try {
                const formData = new URLSearchParams({ email: email, pass: password });

                // A App Móvel envia o cabeçalho 'X-Requested-With' para o PHP saber que é JSON
                const response = await fetch('auth/auth.php', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest' // Identificador para o PHP
                    },
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    
                    if (rememberMe) {
                        // Salva o Token no Storage Nativo
                        await Storage.set({ key: 'auth_token', value: data.user.token.toString() });
                        await Storage.set({ key: 'user_id', value: data.user.id.toString() });
                        await Storage.set({ key: 'user_role', value: data.user.role.toString() });
                    } else {
                         // Limpa qualquer token se o utilizador não quiser persistência
                         await Storage.clear();
                    }

                    window.location.href = data.redirect_route;

                } else {
                    alert('Erro no Login: ' + data.message);
                    button.textContent = 'Login';
                    button.disabled = false;
                }
            } catch (error) {
                console.error('Erro de rede/API:', error);
                alert('Erro de comunicação com o servidor. Verifique a sua ligação.');
                button.textContent = 'Login';
                button.disabled = false;
            }
        });
    }
});


// FUNÇÃO DE LOGOUT (Para usar no driver.php)
async function mobileLogout() {
    await Storage.clear(); 
    // Manda para o PHP para terminar a sessão do servidor também
    window.location.href = '/Includes/dist/pages/logout.php'; 
}