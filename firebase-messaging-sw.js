// firebase-messaging-sw.js

importScripts('https://www.gstatic.com/firebasejs/9.22.1/firebase-app.js');
importScripts('https://www.gstatic.com/firebasejs/9.22.1/firebase-messaging.js');

// Configuração do Firebase
const firebaseConfig = {
  apiKey: "BECXD5Aj5_o9zNq2VNDsCDguVl2hCoS9u0Oty-KG79nKd-3WnGEn4ey8Zm7NCcuVufLuH-hAnAnLEFlGWw7w7EM",
  authDomain: "tgameajuda.firebaseapp.com",
  projectId: "tgameajuda",
  storageBucket: "tgameajuda.appspot.com",
  messagingSenderId: "74941945706",
  appId: "1:74941945706:web:9f0da4e18bb9247a3bb713",
  measurementId: "G-J6NCJKCF63"
};

firebase.initializeApp(firebaseConfig);

const messaging = firebase.messaging();

// Recebe a notificação em segundo plano
messaging.onBackgroundMessage(function(payload) {
  console.log("Mensagem em segundo plano recebida: ", payload);

  const notificationTitle = 'Nova versão disponível!';
  const notificationOptions = {
    body: payload.notification.body,
    icon: '/icon.png'
  };

  self.registration.showNotification(notificationTitle, notificationOptions);
});
