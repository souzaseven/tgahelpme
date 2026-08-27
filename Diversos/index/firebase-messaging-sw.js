importScripts('https://www.gstatic.com/firebasejs/9.22.1/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/9.22.1/firebase-messaging-compat.js');

firebase.initializeApp({
    apiKey: "BECXD5Aj5_o9zNq2VNDsCDguVl2hCoS9u0Oty-KG79nKd-3WnGEn4ey8Zm7NCcuVufLuH-hAnAnLEFlGWw7w7EM",
    authDomain: "tgameajuda.firebaseapp.com",
    projectId: "tgameajuda",
    storageBucket: "tgameajuda.appspot.com",
    messagingSenderId: "74941945706",
    appId: "1:74941945706:web:9f0da4e18bb9247a3bb713",
    measurementId: "G-J6NCJKCF63"
});

const messaging = firebase.messaging();

messaging.onBackgroundMessage(function(payload) {
    const { title, body, icon } = payload.notification || {};
    self.registration.showNotification(title || 'Notificação', {
        body: body || '',
        icon: icon || '/favicon.ico'
    });
});
