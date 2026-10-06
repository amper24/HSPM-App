import Vue from 'vue';
import App from './App.vue';
import store from './store';
import '../css/app.css';

Vue.config.productionTip = false;
new Vue({ store, render: createElement => createElement(App) }).$mount('#app');
