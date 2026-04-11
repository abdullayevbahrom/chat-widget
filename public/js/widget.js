var ChatWidgetSDK = (function() {
	//#region \0rolldown/runtime.js
	var __create = Object.create;
	var __defProp = Object.defineProperty;
	var __getOwnPropDesc = Object.getOwnPropertyDescriptor;
	var __getOwnPropNames = Object.getOwnPropertyNames;
	var __getProtoOf = Object.getPrototypeOf;
	var __hasOwnProp = Object.prototype.hasOwnProperty;
	var __esmMin = (fn, res) => () => (fn && (res = fn(fn = 0)), res);
	var __commonJSMin = (cb, mod) => () => (mod || cb((mod = { exports: {} }).exports, mod), mod.exports);
	var __copyProps = (to, from, except, desc) => {
		if (from && typeof from === "object" || typeof from === "function") for (var keys = __getOwnPropNames(from), i = 0, n = keys.length, key; i < n; i++) {
			key = keys[i];
			if (!__hasOwnProp.call(to, key) && key !== except) __defProp(to, key, {
				get: ((k) => from[k]).bind(null, key),
				enumerable: !(desc = __getOwnPropDesc(from, key)) || desc.enumerable
			});
		}
		return to;
	};
	var __toESM = (mod, isNodeMode, target) => (target = mod != null ? __create(__getProtoOf(mod)) : {}, __copyProps(isNodeMode || !mod || !mod.__esModule ? __defProp(target, "default", {
		value: mod,
		enumerable: true
	}) : target, mod));
	//#endregion
	//#region node_modules/laravel-echo/dist/echo.js
	function g(s) {
		try {
			return Reflect.construct(String, [], s), !0;
		} catch {
			return !1;
		}
	}
	var u, d, l, f, w, _, b, v, C, c, k, y, m, h, r, o, S, p, E;
	var init_echo = __esmMin((() => {
		u = class {
			constructor() {
				this.notificationCreatedEvent = ".Illuminate\\Notifications\\Events\\BroadcastNotificationCreated";
			}
			/**
			* Listen for a whisper event on the channel instance.
			*/
			listenForWhisper(e, t) {
				return this.listen(".client-" + e, t);
			}
			/**
			* Listen for an event on the channel instance.
			*/
			notification(e) {
				return this.listen(this.notificationCreatedEvent, e);
			}
			/**
			* Stop listening for notification events on the channel instance.
			*/
			stopListeningForNotification(e) {
				return this.stopListening(this.notificationCreatedEvent, e);
			}
			/**
			* Stop listening for a whisper event on the channel instance.
			*/
			stopListeningForWhisper(e, t) {
				return this.stopListening(".client-" + e, t);
			}
		};
		d = class {
			/**
			* Create a new class instance.
			*/
			constructor(e) {
				this.namespace = e;
			}
			/**
			* Format the given event name.
			*/
			format(e) {
				return [".", "\\"].includes(e.charAt(0)) ? e.substring(1) : (this.namespace && (e = this.namespace + "." + e), e.replace(/\./g, "\\"));
			}
			/**
			* Set the event namespace.
			*/
			setNamespace(e) {
				this.namespace = e;
			}
		};
		l = class extends u {
			/**
			* Create a new class instance.
			*/
			constructor(e, t, n) {
				super(), this.name = t, this.pusher = e, this.options = n, this.eventFormatter = new d(this.options.namespace), this.subscribe();
			}
			/**
			* Subscribe to a Pusher channel.
			*/
			subscribe() {
				this.subscription = this.pusher.subscribe(this.name);
			}
			/**
			* Unsubscribe from a Pusher channel.
			*/
			unsubscribe() {
				this.pusher.unsubscribe(this.name);
			}
			/**
			* Listen for an event on the channel instance.
			*/
			listen(e, t) {
				return this.on(this.eventFormatter.format(e), t), this;
			}
			/**
			* Listen for all events on the channel instance.
			*/
			listenToAll(e) {
				return this.subscription.bind_global((t, n) => {
					if (t.startsWith("pusher:")) return;
					let i = String(this.options.namespace ?? "").replace(/\./g, "\\");
					e(t.startsWith(i) ? t.substring(i.length + 1) : "." + t, n);
				}), this;
			}
			/**
			* Stop listening for an event on the channel instance.
			*/
			stopListening(e, t) {
				return t ? this.subscription.unbind(this.eventFormatter.format(e), t) : this.subscription.unbind(this.eventFormatter.format(e)), this;
			}
			/**
			* Stop listening for all events on the channel instance.
			*/
			stopListeningToAll(e) {
				return e ? this.subscription.unbind_global(e) : this.subscription.unbind_global(), this;
			}
			/**
			* Register a callback to be called anytime a subscription succeeds.
			*/
			subscribed(e) {
				return this.on("pusher:subscription_succeeded", () => {
					e();
				}), this;
			}
			/**
			* Register a callback to be called anytime a subscription error occurs.
			*/
			error(e) {
				return this.on("pusher:subscription_error", (t) => {
					e(t);
				}), this;
			}
			/**
			* Bind a channel to an event.
			*/
			on(e, t) {
				return this.subscription.bind(e, t), this;
			}
		};
		f = class extends l {
			/**
			* Send a whisper event to other clients in the channel.
			*/
			whisper(e, t) {
				return this.pusher.channels.channels[this.name].trigger(`client-${e}`, t), this;
			}
		};
		w = class extends l {
			/**
			* Send a whisper event to other clients in the channel.
			*/
			whisper(e, t) {
				return this.pusher.channels.channels[this.name].trigger(`client-${e}`, t), this;
			}
		};
		_ = class extends f {
			/**
			* Register a callback to be called anytime the member list changes.
			*/
			here(e) {
				return this.on("pusher:subscription_succeeded", (t) => {
					e(Object.keys(t.members).map((n) => t.members[n]));
				}), this;
			}
			/**
			* Listen for someone joining the channel.
			*/
			joining(e) {
				return this.on("pusher:member_added", (t) => {
					e(t.info);
				}), this;
			}
			/**
			* Send a whisper event to other clients in the channel.
			*/
			whisper(e, t) {
				return this.pusher.channels.channels[this.name].trigger(`client-${e}`, t), this;
			}
			/**
			* Listen for someone leaving the channel.
			*/
			leaving(e) {
				return this.on("pusher:member_removed", (t) => {
					e(t.info);
				}), this;
			}
		};
		b = class extends u {
			/**
			* Create a new class instance.
			*/
			constructor(e, t, n) {
				super(), this.events = {}, this.listeners = {}, this.name = t, this.socket = e, this.options = n, this.eventFormatter = new d(this.options.namespace), this.subscribe();
			}
			/**
			* Subscribe to a Socket.io channel.
			*/
			subscribe() {
				this.socket.emit("subscribe", {
					channel: this.name,
					auth: this.options.auth || {}
				});
			}
			/**
			* Unsubscribe from channel and ubind event callbacks.
			*/
			unsubscribe() {
				this.unbind(), this.socket.emit("unsubscribe", {
					channel: this.name,
					auth: this.options.auth || {}
				});
			}
			/**
			* Listen for an event on the channel instance.
			*/
			listen(e, t) {
				return this.on(this.eventFormatter.format(e), t), this;
			}
			/**
			* Stop listening for an event on the channel instance.
			*/
			stopListening(e, t) {
				return this.unbindEvent(this.eventFormatter.format(e), t), this;
			}
			/**
			* Register a callback to be called anytime a subscription succeeds.
			*/
			subscribed(e) {
				return this.on("connect", (t) => {
					e(t);
				}), this;
			}
			/**
			* Register a callback to be called anytime an error occurs.
			*/
			error(e) {
				return this;
			}
			/**
			* Bind the channel's socket to an event and store the callback.
			*/
			on(e, t) {
				return this.listeners[e] = this.listeners[e] || [], this.events[e] || (this.events[e] = (n, i) => {
					this.name === n && this.listeners[e] && this.listeners[e].forEach((a) => a(i));
				}, this.socket.on(e, this.events[e])), this.listeners[e].push(t), this;
			}
			/**
			* Unbind the channel's socket from all stored event callbacks.
			*/
			unbind() {
				Object.keys(this.events).forEach((e) => {
					this.unbindEvent(e);
				});
			}
			/**
			* Unbind the listeners for the given event.
			*/
			unbindEvent(e, t) {
				this.listeners[e] = this.listeners[e] || [], t && (this.listeners[e] = this.listeners[e].filter((n) => n !== t)), (!t || this.listeners[e].length === 0) && (this.events[e] && (this.socket.removeListener(e, this.events[e]), delete this.events[e]), delete this.listeners[e]);
			}
		};
		v = class extends b {
			/**
			* Send a whisper event to other clients in the channel.
			*/
			whisper(e, t) {
				return this.socket.emit("client event", {
					channel: this.name,
					event: `client-${e}`,
					data: t
				}), this;
			}
		};
		C = class extends v {
			/**
			* Register a callback to be called anytime the member list changes.
			*/
			here(e) {
				return this.on("presence:subscribed", (t) => {
					e(t.map((n) => n.user_info));
				}), this;
			}
			/**
			* Listen for someone joining the channel.
			*/
			joining(e) {
				return this.on("presence:joining", (t) => e(t.user_info)), this;
			}
			/**
			* Send a whisper event to other clients in the channel.
			*/
			whisper(e, t) {
				return this.socket.emit("client event", {
					channel: this.name,
					event: `client-${e}`,
					data: t
				}), this;
			}
			/**
			* Listen for someone leaving the channel.
			*/
			leaving(e) {
				return this.on("presence:leaving", (t) => e(t.user_info)), this;
			}
		};
		c = class extends u {
			/**
			* Subscribe to a channel.
			*/
			subscribe() {}
			/**
			* Unsubscribe from a channel.
			*/
			unsubscribe() {}
			/**
			* Listen for an event on the channel instance.
			*/
			listen(e, t) {
				return this;
			}
			/**
			* Listen for all events on the channel instance.
			*/
			listenToAll(e) {
				return this;
			}
			/**
			* Stop listening for an event on the channel instance.
			*/
			stopListening(e, t) {
				return this;
			}
			/**
			* Register a callback to be called anytime a subscription succeeds.
			*/
			subscribed(e) {
				return this;
			}
			/**
			* Register a callback to be called anytime an error occurs.
			*/
			error(e) {
				return this;
			}
			/**
			* Bind a channel to an event.
			*/
			on(e, t) {
				return this;
			}
		};
		k = class extends c {
			/**
			* Send a whisper event to other clients in the channel.
			*/
			whisper(e, t) {
				return this;
			}
		};
		y = class extends c {
			/**
			* Send a whisper event to other clients in the channel.
			*/
			whisper(e, t) {
				return this;
			}
		};
		m = class extends k {
			/**
			* Register a callback to be called anytime the member list changes.
			*/
			here(e) {
				return this;
			}
			/**
			* Listen for someone joining the channel.
			*/
			joining(e) {
				return this;
			}
			/**
			* Send a whisper event to other clients in the channel.
			*/
			whisper(e, t) {
				return this;
			}
			/**
			* Listen for someone leaving the channel.
			*/
			leaving(e) {
				return this;
			}
		};
		h = class h {
			/**
			* Create a new class instance.
			*/
			constructor(e) {
				this.setOptions(e), this.connect();
			}
			/**
			* Merge the custom options with the defaults.
			*/
			setOptions(e) {
				this.options = {
					...h._defaultOptions,
					...e,
					broadcaster: e.broadcaster
				};
				let t = this.csrfToken();
				t && (this.options.auth.headers["X-CSRF-TOKEN"] = t, this.options.userAuthentication.headers["X-CSRF-TOKEN"] = t), t = this.options.bearerToken, t && (this.options.auth.headers.Authorization = "Bearer " + t, this.options.userAuthentication.headers.Authorization = "Bearer " + t);
			}
			/**
			* Extract the CSRF token from the page.
			*/
			csrfToken() {
				var e, t;
				return typeof window < "u" && (e = window.Laravel) != null && e.csrfToken ? window.Laravel.csrfToken : this.options.csrfToken ? this.options.csrfToken : typeof document < "u" && typeof document.querySelector == "function" ? ((t = document.querySelector("meta[name=\"csrf-token\"]")) == null ? void 0 : t.getAttribute("content")) ?? null : null;
			}
		};
		h._defaultOptions = {
			auth: { headers: {} },
			authEndpoint: "/broadcasting/auth",
			userAuthentication: {
				endpoint: "/broadcasting/user-auth",
				headers: {}
			},
			csrfToken: null,
			bearerToken: null,
			host: null,
			key: null,
			namespace: "App.Events"
		};
		r = h;
		o = class extends r {
			constructor() {
				super(...arguments), this.channels = {};
			}
			/**
			* Create a fresh Pusher connection.
			*/
			connect() {
				if (typeof this.options.client < "u") this.pusher = this.options.client;
				else if (this.options.Pusher) this.pusher = new this.options.Pusher(this.options.key, this.options);
				else if (typeof window < "u" && typeof window.Pusher < "u") this.pusher = new window.Pusher(this.options.key, this.options);
				else throw new Error("Pusher client not found. Should be globally available or passed via options.client");
			}
			/**
			* Sign in the user via Pusher user authentication (https://pusher.com/docs/channels/using_channels/user-authentication/).
			*/
			signin() {
				this.pusher.signin();
			}
			/**
			* Listen for an event on a channel instance.
			*/
			listen(e, t, n) {
				return this.channel(e).listen(t, n);
			}
			/**
			* Get a channel instance by name.
			*/
			channel(e) {
				return this.channels[e] || (this.channels[e] = new l(this.pusher, e, this.options)), this.channels[e];
			}
			/**
			* Get a private channel instance by name.
			*/
			privateChannel(e) {
				return this.channels["private-" + e] || (this.channels["private-" + e] = new f(this.pusher, "private-" + e, this.options)), this.channels["private-" + e];
			}
			/**
			* Get a private encrypted channel instance by name.
			*/
			encryptedPrivateChannel(e) {
				return this.channels["private-encrypted-" + e] || (this.channels["private-encrypted-" + e] = new w(this.pusher, "private-encrypted-" + e, this.options)), this.channels["private-encrypted-" + e];
			}
			/**
			* Get a presence channel instance by name.
			*/
			presenceChannel(e) {
				return this.channels["presence-" + e] || (this.channels["presence-" + e] = new _(this.pusher, "presence-" + e, this.options)), this.channels["presence-" + e];
			}
			/**
			* Leave the given channel, as well as its private and presence variants.
			*/
			leave(e) {
				[
					e,
					"private-" + e,
					"private-encrypted-" + e,
					"presence-" + e
				].forEach((n) => {
					this.leaveChannel(n);
				});
			}
			/**
			* Leave the given channel.
			*/
			leaveChannel(e) {
				this.channels[e] && (this.channels[e].unsubscribe(), delete this.channels[e]);
			}
			/**
			* Get the socket ID for the connection.
			*/
			socketId() {
				return this.pusher.connection.socket_id;
			}
			/**
			* Get the current connection status.
			*/
			connectionStatus() {
				const e = this.pusher.connection.state;
				switch (e) {
					case "connected":
					case "connecting": return e;
					case "failed":
					case "unavailable": return "failed";
					default: return "disconnected";
				}
			}
			/**
			* Subscribe to connection status changes.
			*/
			onConnectionChange(e) {
				const t = () => {
					e(this.connectionStatus());
				}, n = [
					"state_change",
					"connected",
					"disconnected"
				];
				return n.forEach((i) => {
					this.pusher.connection.bind(i, t);
				}), () => {
					n.forEach((i) => {
						this.pusher.connection.unbind(i, t);
					});
				};
			}
			/**
			* Disconnect Pusher connection.
			*/
			disconnect() {
				this.pusher.disconnect();
			}
		};
		S = class extends r {
			constructor() {
				super(...arguments), this.channels = {};
			}
			/**
			* Create a fresh Socket.io connection.
			*/
			connect() {
				this.socket = this.getSocketIO()(this.options.host ?? void 0, this.options), this.socket.io.on("reconnect", () => {
					Object.values(this.channels).forEach((t) => {
						t.subscribe();
					});
				});
			}
			/**
			* Get socket.io module from global scope or options.
			*/
			getSocketIO() {
				if (typeof this.options.client < "u") return this.options.client;
				if (typeof window < "u" && typeof window.io < "u") return window.io;
				throw new Error("Socket.io client not found. Should be globally available or passed via options.client");
			}
			/**
			* Listen for an event on a channel instance.
			*/
			listen(e, t, n) {
				return this.channel(e).listen(t, n);
			}
			/**
			* Get a channel instance by name.
			*/
			channel(e) {
				return this.channels[e] || (this.channels[e] = new b(this.socket, e, this.options)), this.channels[e];
			}
			/**
			* Get a private channel instance by name.
			*/
			privateChannel(e) {
				return this.channels["private-" + e] || (this.channels["private-" + e] = new v(this.socket, "private-" + e, this.options)), this.channels["private-" + e];
			}
			/**
			* Get a presence channel instance by name.
			*/
			presenceChannel(e) {
				return this.channels["presence-" + e] || (this.channels["presence-" + e] = new C(this.socket, "presence-" + e, this.options)), this.channels["presence-" + e];
			}
			/**
			* Leave the given channel, as well as its private and presence variants.
			*/
			leave(e) {
				[
					e,
					"private-" + e,
					"presence-" + e
				].forEach((n) => {
					this.leaveChannel(n);
				});
			}
			/**
			* Leave the given channel.
			*/
			leaveChannel(e) {
				this.channels[e] && (this.channels[e].unsubscribe(), delete this.channels[e]);
			}
			/**
			* Get the socket ID for the connection.
			*/
			socketId() {
				return this.socket.id;
			}
			/**
			* Get the current connection status.
			*/
			connectionStatus() {
				return this.socket.connected ? "connected" : this.socket.io._reconnecting ? "reconnecting" : this.socket.id !== void 0 ? "disconnected" : "connecting";
			}
			/**
			* Subscribe to connection status changes.
			*/
			onConnectionChange(e) {
				const t = () => {
					e(this.connectionStatus());
				}, n = [
					"connect",
					"disconnect",
					"connect_error",
					"reconnect_attempt",
					"reconnect",
					"reconnect_error",
					"reconnect_failed"
				];
				return n.forEach((i) => {
					this.socket.on(i, t);
				}), () => {
					n.forEach((i) => {
						this.socket.off(i, t);
					});
				};
			}
			/**
			* Disconnect Socketio connection.
			*/
			disconnect() {
				this.socket.disconnect();
			}
		};
		p = class extends r {
			constructor() {
				super(...arguments), this.channels = {};
			}
			/**
			* Create a fresh connection.
			*/
			connect() {}
			/**
			* Listen for an event on a channel instance.
			*/
			listen(e, t, n) {
				return new c();
			}
			/**
			* Get a channel instance by name.
			*/
			channel(e) {
				return new c();
			}
			/**
			* Get a private channel instance by name.
			*/
			privateChannel(e) {
				return new k();
			}
			/**
			* Get a private encrypted channel instance by name.
			*/
			encryptedPrivateChannel(e) {
				return new y();
			}
			/**
			* Get a presence channel instance by name.
			*/
			presenceChannel(e) {
				return new m();
			}
			/**
			* Leave the given channel, as well as its private and presence variants.
			*/
			leave(e) {}
			/**
			* Leave the given channel.
			*/
			leaveChannel(e) {}
			/**
			* Get the socket ID for the connection.
			*/
			socketId() {
				return "fake-socket-id";
			}
			/**
			* Get the current connection status.
			*/
			connectionStatus() {
				return "connected";
			}
			/**
			* Subscribe to connection status changes.
			*/
			onConnectionChange(e) {
				return () => {};
			}
			/**
			* Disconnect the connection.
			*/
			disconnect() {}
		};
		E = class {
			/**
			* Create a new class instance.
			*/
			constructor(e) {
				this.options = e, this.connect(), this.options.withoutInterceptors || this.registerInterceptors();
			}
			/**
			* Get a channel instance by name.
			*/
			channel(e) {
				return this.connector.channel(e);
			}
			/**
			* Create a new connection.
			*/
			connect() {
				if (this.options.broadcaster === "reverb") this.connector = new o({
					...this.options,
					cluster: ""
				});
				else if (this.options.broadcaster === "pusher") this.connector = new o(this.options);
				else if (this.options.broadcaster === "ably") this.connector = new o({
					...this.options,
					cluster: "",
					broadcaster: "pusher"
				});
				else if (this.options.broadcaster === "socket.io") this.connector = new S(this.options);
				else if (this.options.broadcaster === "null") this.connector = new p(this.options);
				else if (typeof this.options.broadcaster == "function" && g(this.options.broadcaster)) this.connector = new this.options.broadcaster(this.options);
				else throw new Error(`Broadcaster ${typeof this.options.broadcaster} ${String(this.options.broadcaster)} is not supported.`);
			}
			/**
			* Disconnect from the Echo server.
			*/
			disconnect() {
				this.connector.disconnect();
			}
			/**
			* Get a presence channel instance by name.
			*/
			join(e) {
				return this.connector.presenceChannel(e);
			}
			/**
			* Leave the given channel, as well as its private and presence variants.
			*/
			leave(e) {
				this.connector.leave(e);
			}
			/**
			* Leave the given channel.
			*/
			leaveChannel(e) {
				this.connector.leaveChannel(e);
			}
			/**
			* Leave all channels.
			*/
			leaveAllChannels() {
				for (const e in this.connector.channels) this.leaveChannel(e);
			}
			/**
			* Listen for an event on a channel instance.
			*/
			listen(e, t, n) {
				return this.connector.listen(e, t, n);
			}
			/**
			* Get a private channel instance by name.
			*/
			private(e) {
				return this.connector.privateChannel(e);
			}
			/**
			* Get a private encrypted channel instance by name.
			*/
			encryptedPrivate(e) {
				if (this.connectorSupportsEncryptedPrivateChannels(this.connector)) return this.connector.encryptedPrivateChannel(e);
				throw new Error(`Broadcaster ${typeof this.options.broadcaster} ${String(this.options.broadcaster)} does not support encrypted private channels.`);
			}
			connectorSupportsEncryptedPrivateChannels(e) {
				return e instanceof o || e instanceof p;
			}
			/**
			* Get the Socket ID for the connection.
			*/
			socketId() {
				return this.connector.socketId();
			}
			/**
			* Get the current connection status.
			*/
			connectionStatus() {
				return this.connector.connectionStatus();
			}
			/**
			* Register 3rd party request interceptors. These are used to automatically
			* send a connections socket id to a Laravel app with a X-Socket-Id header.
			*/
			registerInterceptors() {
				typeof Vue < "u" && Vue != null && Vue.http && this.registerVueRequestInterceptor(), typeof axios == "function" && this.registerAxiosRequestInterceptor(), typeof jQuery == "function" && this.registerjQueryAjaxSetup(), typeof Turbo == "object" && this.registerTurboRequestInterceptor();
			}
			/**
			* Register a Vue HTTP interceptor to add the X-Socket-ID header.
			*/
			registerVueRequestInterceptor() {
				Vue.http.interceptors.push((e, t) => {
					this.socketId() && e.headers.set("X-Socket-ID", this.socketId()), t();
				});
			}
			/**
			* Register an Axios HTTP interceptor to add the X-Socket-ID header.
			*/
			registerAxiosRequestInterceptor() {
				axios.interceptors.request.use((e) => (this.socketId() && (e.headers["X-Socket-Id"] = this.socketId()), e));
			}
			/**
			* Register jQuery AjaxPrefilter to add the X-Socket-ID header.
			*/
			registerjQueryAjaxSetup() {
				typeof jQuery.ajax < "u" && jQuery.ajaxPrefilter((e, t, n) => {
					this.socketId() && n.setRequestHeader("X-Socket-Id", this.socketId());
				});
			}
			/**
			* Register the Turbo Request interceptor to add the X-Socket-ID header.
			*/
			registerTurboRequestInterceptor() {
				document.addEventListener("turbo:before-fetch-request", (e) => {
					e.detail.fetchOptions.headers["X-Socket-Id"] = this.socketId();
				});
			}
		};
	}));
	//#endregion
	//#region node_modules/pusher-js/dist/web/pusher.js
	var require_pusher = /* @__PURE__ */ __commonJSMin(((exports, module) => {
		/*!
		* Pusher JavaScript Library v8.5.0
		* https://pusher.com/
		*
		* Copyright 2020, Pusher
		* Released under the MIT licence.
		*/
		(function webpackUniversalModuleDefinition(root, factory) {
			if (typeof exports === "object" && typeof module === "object") module.exports = factory();
			else if (typeof define === "function" && define.amd) define([], factory);
			else if (typeof exports === "object") exports["Pusher"] = factory();
			else root["Pusher"] = factory();
		})(self, () => {
			return (() => {
				var __webpack_modules__ = {
					594(__unused_webpack_module, exports$1) {
						"use strict";
						var __extends = this && this.__extends || (function() {
							var extendStatics = function(d, b) {
								extendStatics = Object.setPrototypeOf || { __proto__: [] } instanceof Array && function(d, b) {
									d.__proto__ = b;
								} || function(d, b) {
									for (var p in b) if (b.hasOwnProperty(p)) d[p] = b[p];
								};
								return extendStatics(d, b);
							};
							return function(d, b) {
								extendStatics(d, b);
								function __() {
									this.constructor = d;
								}
								d.prototype = b === null ? Object.create(b) : (__.prototype = b.prototype, new __());
							};
						})();
						Object.defineProperty(exports$1, "__esModule", { value: true });
						/**
						* Package base64 implements Base64 encoding and decoding.
						*/
						var INVALID_BYTE = 256;
						/**
						* Implements standard Base64 encoding.
						*
						* Operates in constant time.
						*/
						var Coder = function() {
							function Coder(_paddingCharacter) {
								if (_paddingCharacter === void 0) _paddingCharacter = "=";
								this._paddingCharacter = _paddingCharacter;
							}
							Coder.prototype.encodedLength = function(length) {
								if (!this._paddingCharacter) return (length * 8 + 5) / 6 | 0;
								return (length + 2) / 3 * 4 | 0;
							};
							Coder.prototype.encode = function(data) {
								var out = "";
								var i = 0;
								for (; i < data.length - 2; i += 3) {
									var c = data[i] << 16 | data[i + 1] << 8 | data[i + 2];
									out += this._encodeByte(c >>> 18 & 63);
									out += this._encodeByte(c >>> 12 & 63);
									out += this._encodeByte(c >>> 6 & 63);
									out += this._encodeByte(c >>> 0 & 63);
								}
								var left = data.length - i;
								if (left > 0) {
									var c = data[i] << 16 | (left === 2 ? data[i + 1] << 8 : 0);
									out += this._encodeByte(c >>> 18 & 63);
									out += this._encodeByte(c >>> 12 & 63);
									if (left === 2) out += this._encodeByte(c >>> 6 & 63);
									else out += this._paddingCharacter || "";
									out += this._paddingCharacter || "";
								}
								return out;
							};
							Coder.prototype.maxDecodedLength = function(length) {
								if (!this._paddingCharacter) return (length * 6 + 7) / 8 | 0;
								return length / 4 * 3 | 0;
							};
							Coder.prototype.decodedLength = function(s) {
								return this.maxDecodedLength(s.length - this._getPaddingLength(s));
							};
							Coder.prototype.decode = function(s) {
								if (s.length === 0) return new Uint8Array(0);
								var paddingLength = this._getPaddingLength(s);
								var length = s.length - paddingLength;
								var out = new Uint8Array(this.maxDecodedLength(length));
								var op = 0;
								var i = 0;
								var haveBad = 0;
								var v0 = 0, v1 = 0, v2 = 0, v3 = 0;
								for (; i < length - 4; i += 4) {
									v0 = this._decodeChar(s.charCodeAt(i + 0));
									v1 = this._decodeChar(s.charCodeAt(i + 1));
									v2 = this._decodeChar(s.charCodeAt(i + 2));
									v3 = this._decodeChar(s.charCodeAt(i + 3));
									out[op++] = v0 << 2 | v1 >>> 4;
									out[op++] = v1 << 4 | v2 >>> 2;
									out[op++] = v2 << 6 | v3;
									haveBad |= v0 & INVALID_BYTE;
									haveBad |= v1 & INVALID_BYTE;
									haveBad |= v2 & INVALID_BYTE;
									haveBad |= v3 & INVALID_BYTE;
								}
								if (i < length - 1) {
									v0 = this._decodeChar(s.charCodeAt(i));
									v1 = this._decodeChar(s.charCodeAt(i + 1));
									out[op++] = v0 << 2 | v1 >>> 4;
									haveBad |= v0 & INVALID_BYTE;
									haveBad |= v1 & INVALID_BYTE;
								}
								if (i < length - 2) {
									v2 = this._decodeChar(s.charCodeAt(i + 2));
									out[op++] = v1 << 4 | v2 >>> 2;
									haveBad |= v2 & INVALID_BYTE;
								}
								if (i < length - 3) {
									v3 = this._decodeChar(s.charCodeAt(i + 3));
									out[op++] = v2 << 6 | v3;
									haveBad |= v3 & INVALID_BYTE;
								}
								if (haveBad !== 0) throw new Error("Base64Coder: incorrect characters for decoding");
								return out;
							};
							Coder.prototype._encodeByte = function(b) {
								var result = b;
								result += 65;
								result += 25 - b >>> 8 & 6;
								result += 51 - b >>> 8 & -75;
								result += 61 - b >>> 8 & -15;
								result += 62 - b >>> 8 & 3;
								return String.fromCharCode(result);
							};
							Coder.prototype._decodeChar = function(c) {
								var result = INVALID_BYTE;
								result += (42 - c & c - 44) >>> 8 & -INVALID_BYTE + c - 43 + 62;
								result += (46 - c & c - 48) >>> 8 & -INVALID_BYTE + c - 47 + 63;
								result += (47 - c & c - 58) >>> 8 & -INVALID_BYTE + c - 48 + 52;
								result += (64 - c & c - 91) >>> 8 & -INVALID_BYTE + c - 65 + 0;
								result += (96 - c & c - 123) >>> 8 & -INVALID_BYTE + c - 97 + 26;
								return result;
							};
							Coder.prototype._getPaddingLength = function(s) {
								var paddingLength = 0;
								if (this._paddingCharacter) {
									for (var i = s.length - 1; i >= 0; i--) {
										if (s[i] !== this._paddingCharacter) break;
										paddingLength++;
									}
									if (s.length < 4 || paddingLength > 2) throw new Error("Base64Coder: incorrect padding");
								}
								return paddingLength;
							};
							return Coder;
						}();
						exports$1.Coder = Coder;
						var stdCoder = new Coder();
						function encode(data) {
							return stdCoder.encode(data);
						}
						exports$1.encode = encode;
						function decode(s) {
							return stdCoder.decode(s);
						}
						exports$1.decode = decode;
						/**
						* Implements URL-safe Base64 encoding.
						* (Same as Base64, but '+' is replaced with '-', and '/' with '_').
						*
						* Operates in constant time.
						*/
						var URLSafeCoder = function(_super) {
							__extends(URLSafeCoder, _super);
							function URLSafeCoder() {
								return _super !== null && _super.apply(this, arguments) || this;
							}
							URLSafeCoder.prototype._encodeByte = function(b) {
								var result = b;
								result += 65;
								result += 25 - b >>> 8 & 6;
								result += 51 - b >>> 8 & -75;
								result += 61 - b >>> 8 & -13;
								result += 62 - b >>> 8 & 49;
								return String.fromCharCode(result);
							};
							URLSafeCoder.prototype._decodeChar = function(c) {
								var result = INVALID_BYTE;
								result += (44 - c & c - 46) >>> 8 & -INVALID_BYTE + c - 45 + 62;
								result += (94 - c & c - 96) >>> 8 & -INVALID_BYTE + c - 95 + 63;
								result += (47 - c & c - 58) >>> 8 & -INVALID_BYTE + c - 48 + 52;
								result += (64 - c & c - 91) >>> 8 & -INVALID_BYTE + c - 65 + 0;
								result += (96 - c & c - 123) >>> 8 & -INVALID_BYTE + c - 97 + 26;
								return result;
							};
							return URLSafeCoder;
						}(Coder);
						exports$1.URLSafeCoder = URLSafeCoder;
						var urlSafeCoder = new URLSafeCoder();
						function encodeURLSafe(data) {
							return urlSafeCoder.encode(data);
						}
						exports$1.encodeURLSafe = encodeURLSafe;
						function decodeURLSafe(s) {
							return urlSafeCoder.decode(s);
						}
						exports$1.decodeURLSafe = decodeURLSafe;
						exports$1.encodedLength = function(length) {
							return stdCoder.encodedLength(length);
						};
						exports$1.maxDecodedLength = function(length) {
							return stdCoder.maxDecodedLength(length);
						};
						exports$1.decodedLength = function(s) {
							return stdCoder.decodedLength(s);
						};
					},
					978(__unused_webpack_module, exports$2) {
						"use strict";
						var INVALID_UTF8 = "utf8: invalid source encoding";
						/**
						* Decodes the given byte array from UTF-8 into a string.
						* Throws if encoding is invalid.
						*/
						function decode(arr) {
							var chars = [];
							for (var i = 0; i < arr.length; i++) {
								var b = arr[i];
								if (b & 128) {
									var min = void 0;
									if (b < 224) {
										if (i >= arr.length) throw new Error(INVALID_UTF8);
										var n1 = arr[++i];
										if ((n1 & 192) !== 128) throw new Error(INVALID_UTF8);
										b = (b & 31) << 6 | n1 & 63;
										min = 128;
									} else if (b < 240) {
										if (i >= arr.length - 1) throw new Error(INVALID_UTF8);
										var n1 = arr[++i];
										var n2 = arr[++i];
										if ((n1 & 192) !== 128 || (n2 & 192) !== 128) throw new Error(INVALID_UTF8);
										b = (b & 15) << 12 | (n1 & 63) << 6 | n2 & 63;
										min = 2048;
									} else if (b < 248) {
										if (i >= arr.length - 2) throw new Error(INVALID_UTF8);
										var n1 = arr[++i];
										var n2 = arr[++i];
										var n3 = arr[++i];
										if ((n1 & 192) !== 128 || (n2 & 192) !== 128 || (n3 & 192) !== 128) throw new Error(INVALID_UTF8);
										b = (b & 15) << 18 | (n1 & 63) << 12 | (n2 & 63) << 6 | n3 & 63;
										min = 65536;
									} else throw new Error(INVALID_UTF8);
									if (b < min || b >= 55296 && b <= 57343) throw new Error(INVALID_UTF8);
									if (b >= 65536) {
										if (b > 1114111) throw new Error(INVALID_UTF8);
										b -= 65536;
										chars.push(String.fromCharCode(55296 | b >> 10));
										b = 56320 | b & 1023;
									}
								}
								chars.push(String.fromCharCode(b));
							}
							return chars.join("");
						}
						exports$2.D4 = decode;
					},
					721(module$1, __unused_webpack_exports, __webpack_require__) {
						module$1.exports = __webpack_require__(207)["default"];
					},
					207(__unused_webpack_module, __webpack_exports__, __webpack_require__) {
						"use strict";
						__webpack_require__.d(__webpack_exports__, { "default": () => pusher });
						class ScriptReceiverFactory {
							constructor(prefix, name) {
								this.lastId = 0;
								this.prefix = prefix;
								this.name = name;
							}
							create(callback) {
								this.lastId++;
								var number = this.lastId;
								var id = this.prefix + number;
								var name = this.name + "[" + number + "]";
								var called = false;
								var callbackWrapper = function() {
									if (!called) {
										callback.apply(null, arguments);
										called = true;
									}
								};
								this[number] = callbackWrapper;
								return {
									number,
									id,
									name,
									callback: callbackWrapper
								};
							}
							remove(receiver) {
								delete this[receiver.number];
							}
						}
						var ScriptReceivers = new ScriptReceiverFactory("_pusher_script_", "Pusher.ScriptReceivers");
						const defaults = {
							VERSION: "8.5.0",
							PROTOCOL: 7,
							wsPort: 80,
							wssPort: 443,
							wsPath: "",
							httpHost: "sockjs.pusher.com",
							httpPort: 80,
							httpsPort: 443,
							httpPath: "/pusher",
							stats_host: "stats.pusher.com",
							authEndpoint: "/pusher/auth",
							authTransport: "ajax",
							activityTimeout: 12e4,
							pongTimeout: 3e4,
							unavailableTimeout: 1e4,
							userAuthentication: {
								endpoint: "/pusher/user-auth",
								transport: "ajax"
							},
							channelAuthorization: {
								endpoint: "/pusher/auth",
								transport: "ajax"
							},
							cdn_http: "http://js.pusher.com",
							cdn_https: "https://js.pusher.com",
							dependency_suffix: ""
						};
						class DependencyLoader {
							constructor(options) {
								this.options = options;
								this.receivers = options.receivers || ScriptReceivers;
								this.loading = {};
							}
							load(name, options, callback) {
								var self = this;
								if (self.loading[name] && self.loading[name].length > 0) self.loading[name].push(callback);
								else {
									self.loading[name] = [callback];
									var request = runtime.createScriptRequest(self.getPath(name, options));
									var receiver = self.receivers.create(function(error) {
										self.receivers.remove(receiver);
										if (self.loading[name]) {
											var callbacks = self.loading[name];
											delete self.loading[name];
											var successCallback = function(wasSuccessful) {
												if (!wasSuccessful) request.cleanup();
											};
											for (var i = 0; i < callbacks.length; i++) callbacks[i](error, successCallback);
										}
									});
									request.send(receiver);
								}
							}
							getRoot(options) {
								var cdn;
								var protocol = runtime.getDocument().location.protocol;
								if (options && options.useTLS || protocol === "https:") cdn = this.options.cdn_https;
								else cdn = this.options.cdn_http;
								return cdn.replace(/\/*$/, "") + "/" + this.options.version;
							}
							getPath(name, options) {
								return this.getRoot(options) + "/" + name + this.options.suffix + ".js";
							}
						}
						var DependenciesReceivers = new ScriptReceiverFactory("_pusher_dependencies", "Pusher.DependenciesReceivers");
						var Dependencies = new DependencyLoader({
							cdn_http: defaults.cdn_http,
							cdn_https: defaults.cdn_https,
							version: defaults.VERSION,
							suffix: defaults.dependency_suffix,
							receivers: DependenciesReceivers
						});
						const urlStore = {
							baseUrl: "https://pusher.com",
							urls: {
								authenticationEndpoint: { path: "/docs/channels/server_api/authenticating_users" },
								authorizationEndpoint: { path: "/docs/channels/server_api/authorizing-users/" },
								javascriptQuickStart: { path: "/docs/javascript_quick_start" },
								triggeringClientEvents: { path: "/docs/client_api_guide/client_events#trigger-events" },
								encryptedChannelSupport: { fullUrl: "https://github.com/pusher/pusher-js/tree/cc491015371a4bde5743d1c87a0fbac0feb53195#encrypted-channel-support" }
							}
						};
						const buildLogSuffix = function(key) {
							const urlPrefix = "See:";
							const urlObj = urlStore.urls[key];
							if (!urlObj) return "";
							let url;
							if (urlObj.fullUrl) url = urlObj.fullUrl;
							else if (urlObj.path) url = urlStore.baseUrl + urlObj.path;
							if (!url) return "";
							return `${urlPrefix} ${url}`;
						};
						const url_store = { buildLogSuffix };
						var AuthRequestType;
						(function(AuthRequestType) {
							AuthRequestType["UserAuthentication"] = "user-authentication";
							AuthRequestType["ChannelAuthorization"] = "channel-authorization";
						})(AuthRequestType || (AuthRequestType = {}));
						class BadEventName extends Error {
							constructor(msg) {
								super(msg);
								Object.setPrototypeOf(this, new.target.prototype);
							}
						}
						class BadChannelName extends Error {
							constructor(msg) {
								super(msg);
								Object.setPrototypeOf(this, new.target.prototype);
							}
						}
						class RequestTimedOut extends Error {
							constructor(msg) {
								super(msg);
								Object.setPrototypeOf(this, new.target.prototype);
							}
						}
						class TransportPriorityTooLow extends Error {
							constructor(msg) {
								super(msg);
								Object.setPrototypeOf(this, new.target.prototype);
							}
						}
						class TransportClosed extends Error {
							constructor(msg) {
								super(msg);
								Object.setPrototypeOf(this, new.target.prototype);
							}
						}
						class UnsupportedFeature extends Error {
							constructor(msg) {
								super(msg);
								Object.setPrototypeOf(this, new.target.prototype);
							}
						}
						class UnsupportedTransport extends Error {
							constructor(msg) {
								super(msg);
								Object.setPrototypeOf(this, new.target.prototype);
							}
						}
						class UnsupportedStrategy extends Error {
							constructor(msg) {
								super(msg);
								Object.setPrototypeOf(this, new.target.prototype);
							}
						}
						class HTTPAuthError extends Error {
							constructor(status, msg) {
								super(msg);
								this.status = status;
								Object.setPrototypeOf(this, new.target.prototype);
							}
						}
						const ajax = function(context, query, authOptions, authRequestType, callback) {
							const xhr = runtime.createXHR();
							xhr.open("POST", authOptions.endpoint, true);
							xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
							for (var headerName in authOptions.headers) xhr.setRequestHeader(headerName, authOptions.headers[headerName]);
							if (authOptions.headersProvider != null) {
								let dynamicHeaders = authOptions.headersProvider();
								for (var headerName in dynamicHeaders) xhr.setRequestHeader(headerName, dynamicHeaders[headerName]);
							}
							xhr.onreadystatechange = function() {
								if (xhr.readyState === 4) if (xhr.status === 200) {
									let data;
									let parsed = false;
									try {
										data = JSON.parse(xhr.responseText);
										parsed = true;
									} catch (e) {
										callback(new HTTPAuthError(200, `JSON returned from ${authRequestType.toString()} endpoint was invalid, yet status code was 200. Data was: ${xhr.responseText}`), null);
									}
									if (parsed) callback(null, data);
								} else {
									let suffix = "";
									switch (authRequestType) {
										case AuthRequestType.UserAuthentication:
											suffix = url_store.buildLogSuffix("authenticationEndpoint");
											break;
										case AuthRequestType.ChannelAuthorization:
											suffix = `Clients must be authorized to join private or presence channels. ${url_store.buildLogSuffix("authorizationEndpoint")}`;
											break;
									}
									callback(new HTTPAuthError(xhr.status, `Unable to retrieve auth string from ${authRequestType.toString()} endpoint - received status: ${xhr.status} from ${authOptions.endpoint}. ${suffix}`), null);
								}
							};
							xhr.send(query);
							return xhr;
						};
						const xhr_auth = ajax;
						function encode(s) {
							return btoa(utob(s));
						}
						var fromCharCode = String.fromCharCode;
						var b64chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";
						var b64tab = {};
						for (var i = 0, l = b64chars.length; i < l; i++) b64tab[b64chars.charAt(i)] = i;
						var cb_utob = function(c) {
							var cc = c.charCodeAt(0);
							return cc < 128 ? c : cc < 2048 ? fromCharCode(192 | cc >>> 6) + fromCharCode(128 | cc & 63) : fromCharCode(224 | cc >>> 12 & 15) + fromCharCode(128 | cc >>> 6 & 63) + fromCharCode(128 | cc & 63);
						};
						var utob = function(u) {
							return u.replace(/[^\x00-\x7F]/g, cb_utob);
						};
						var cb_encode = function(ccc) {
							var padlen = [
								0,
								2,
								1
							][ccc.length % 3];
							var ord = ccc.charCodeAt(0) << 16 | (ccc.length > 1 ? ccc.charCodeAt(1) : 0) << 8 | (ccc.length > 2 ? ccc.charCodeAt(2) : 0);
							return [
								b64chars.charAt(ord >>> 18),
								b64chars.charAt(ord >>> 12 & 63),
								padlen >= 2 ? "=" : b64chars.charAt(ord >>> 6 & 63),
								padlen >= 1 ? "=" : b64chars.charAt(ord & 63)
							].join("");
						};
						var btoa = window.btoa || function(b) {
							return b.replace(/[\s\S]{1,3}/g, cb_encode);
						};
						class Timer {
							constructor(set, clear, delay, callback) {
								this.clear = clear;
								this.timer = set(() => {
									if (this.timer) this.timer = callback(this.timer);
								}, delay);
							}
							isRunning() {
								return this.timer !== null;
							}
							ensureAborted() {
								if (this.timer) {
									this.clear(this.timer);
									this.timer = null;
								}
							}
						}
						const abstract_timer = Timer;
						function timers_clearTimeout(timer) {
							window.clearTimeout(timer);
						}
						function timers_clearInterval(timer) {
							window.clearInterval(timer);
						}
						class OneOffTimer extends abstract_timer {
							constructor(delay, callback) {
								super(setTimeout, timers_clearTimeout, delay, function(timer) {
									callback();
									return null;
								});
							}
						}
						class PeriodicTimer extends abstract_timer {
							constructor(delay, callback) {
								super(setInterval, timers_clearInterval, delay, function(timer) {
									callback();
									return timer;
								});
							}
						}
						const util = {
							now() {
								if (Date.now) return Date.now();
								else return (/* @__PURE__ */ new Date()).valueOf();
							},
							defer(callback) {
								return new OneOffTimer(0, callback);
							},
							method(name, ...args) {
								var boundArguments = Array.prototype.slice.call(arguments, 1);
								return function(object) {
									return object[name].apply(object, boundArguments.concat(arguments));
								};
							}
						};
						function extend(target, ...sources) {
							for (var i = 0; i < sources.length; i++) {
								var extensions = sources[i];
								for (var property in extensions) if (extensions[property] && extensions[property].constructor && extensions[property].constructor === Object) target[property] = extend(target[property] || {}, extensions[property]);
								else target[property] = extensions[property];
							}
							return target;
						}
						function stringify() {
							var m = ["Pusher"];
							for (var i = 0; i < arguments.length; i++) if (typeof arguments[i] === "string") m.push(arguments[i]);
							else m.push(safeJSONStringify(arguments[i]));
							return m.join(" : ");
						}
						function arrayIndexOf(array, item) {
							var nativeIndexOf = Array.prototype.indexOf;
							if (array === null) return -1;
							if (nativeIndexOf && array.indexOf === nativeIndexOf) return array.indexOf(item);
							for (var i = 0, l = array.length; i < l; i++) if (array[i] === item) return i;
							return -1;
						}
						function objectApply(object, f) {
							for (var key in object) if (Object.prototype.hasOwnProperty.call(object, key)) f(object[key], key, object);
						}
						function keys(object) {
							var keys = [];
							objectApply(object, function(_, key) {
								keys.push(key);
							});
							return keys;
						}
						function values(object) {
							var values = [];
							objectApply(object, function(value) {
								values.push(value);
							});
							return values;
						}
						function apply(array, f, context) {
							for (var i = 0; i < array.length; i++) f.call(context || window, array[i], i, array);
						}
						function map(array, f) {
							var result = [];
							for (var i = 0; i < array.length; i++) result.push(f(array[i], i, array, result));
							return result;
						}
						function mapObject(object, f) {
							var result = {};
							objectApply(object, function(value, key) {
								result[key] = f(value);
							});
							return result;
						}
						function filter(array, test) {
							test = test || function(value) {
								return !!value;
							};
							var result = [];
							for (var i = 0; i < array.length; i++) if (test(array[i], i, array, result)) result.push(array[i]);
							return result;
						}
						function filterObject(object, test) {
							var result = {};
							objectApply(object, function(value, key) {
								if (test && test(value, key, object, result) || Boolean(value)) result[key] = value;
							});
							return result;
						}
						function flatten(object) {
							var result = [];
							objectApply(object, function(value, key) {
								result.push([key, value]);
							});
							return result;
						}
						function any(array, test) {
							for (var i = 0; i < array.length; i++) if (test(array[i], i, array)) return true;
							return false;
						}
						function collections_all(array, test) {
							for (var i = 0; i < array.length; i++) if (!test(array[i], i, array)) return false;
							return true;
						}
						function encodeParamsObject(data) {
							return mapObject(data, function(value) {
								if (typeof value === "object") value = safeJSONStringify(value);
								return encodeURIComponent(encode(value.toString()));
							});
						}
						function buildQueryString(data) {
							return map(flatten(encodeParamsObject(filterObject(data, function(value) {
								return value !== void 0;
							}))), util.method("join", "=")).join("&");
						}
						function decycleObject(object) {
							var objects = [], paths = [];
							return (function derez(value, path) {
								var i, name, nu;
								switch (typeof value) {
									case "object":
										if (!value) return null;
										for (i = 0; i < objects.length; i += 1) if (objects[i] === value) return { $ref: paths[i] };
										objects.push(value);
										paths.push(path);
										if (Object.prototype.toString.apply(value) === "[object Array]") {
											nu = [];
											for (i = 0; i < value.length; i += 1) nu[i] = derez(value[i], path + "[" + i + "]");
										} else {
											nu = {};
											for (name in value) if (Object.prototype.hasOwnProperty.call(value, name)) nu[name] = derez(value[name], path + "[" + JSON.stringify(name) + "]");
										}
										return nu;
									case "number":
									case "string":
									case "boolean": return value;
								}
							})(object, "$");
						}
						function safeJSONStringify(source) {
							try {
								return JSON.stringify(source);
							} catch (e) {
								return JSON.stringify(decycleObject(source));
							}
						}
						class Logger {
							constructor() {
								this.globalLog = (message) => {
									if (window.console && window.console.log) window.console.log(message);
								};
							}
							debug(...args) {
								this.log(this.globalLog, args);
							}
							warn(...args) {
								this.log(this.globalLogWarn, args);
							}
							error(...args) {
								this.log(this.globalLogError, args);
							}
							globalLogWarn(message) {
								if (window.console && window.console.warn) window.console.warn(message);
								else this.globalLog(message);
							}
							globalLogError(message) {
								if (window.console && window.console.error) window.console.error(message);
								else this.globalLogWarn(message);
							}
							log(defaultLoggingFunction, ...args) {
								var message = stringify.apply(this, arguments);
								if (pusher.log) pusher.log(message);
								else if (pusher.logToConsole) defaultLoggingFunction.bind(this)(message);
							}
						}
						const logger = new Logger();
						var jsonp = function(context, query, authOptions, authRequestType, callback) {
							if (authOptions.headers !== void 0 || authOptions.headersProvider != null) logger.warn(`To send headers with the ${authRequestType.toString()} request, you must use AJAX, rather than JSONP.`);
							var callbackName = context.nextAuthCallbackID.toString();
							context.nextAuthCallbackID++;
							var document = context.getDocument();
							var script = document.createElement("script");
							context.auth_callbacks[callbackName] = function(data) {
								callback(null, data);
							};
							var callback_name = "Pusher.auth_callbacks['" + callbackName + "']";
							script.src = authOptions.endpoint + "?callback=" + encodeURIComponent(callback_name) + "&" + query;
							var head = document.getElementsByTagName("head")[0] || document.documentElement;
							head.insertBefore(script, head.firstChild);
						};
						const jsonp_auth = jsonp;
						class ScriptRequest {
							constructor(src) {
								this.src = src;
							}
							send(receiver) {
								var self = this;
								var errorString = "Error loading " + self.src;
								self.script = document.createElement("script");
								self.script.id = receiver.id;
								self.script.src = self.src;
								self.script.type = "text/javascript";
								self.script.charset = "UTF-8";
								if (self.script.addEventListener) {
									self.script.onerror = function() {
										receiver.callback(errorString);
									};
									self.script.onload = function() {
										receiver.callback(null);
									};
								} else self.script.onreadystatechange = function() {
									if (self.script.readyState === "loaded" || self.script.readyState === "complete") receiver.callback(null);
								};
								if (self.script.async === void 0 && document.attachEvent && /opera/i.test(navigator.userAgent)) {
									self.errorScript = document.createElement("script");
									self.errorScript.id = receiver.id + "_error";
									self.errorScript.text = receiver.name + "('" + errorString + "');";
									self.script.async = self.errorScript.async = false;
								} else self.script.async = true;
								var head = document.getElementsByTagName("head")[0];
								head.insertBefore(self.script, head.firstChild);
								if (self.errorScript) head.insertBefore(self.errorScript, self.script.nextSibling);
							}
							cleanup() {
								if (this.script) {
									this.script.onload = this.script.onerror = null;
									this.script.onreadystatechange = null;
								}
								if (this.script && this.script.parentNode) this.script.parentNode.removeChild(this.script);
								if (this.errorScript && this.errorScript.parentNode) this.errorScript.parentNode.removeChild(this.errorScript);
								this.script = null;
								this.errorScript = null;
							}
						}
						class JSONPRequest {
							constructor(url, data) {
								this.url = url;
								this.data = data;
							}
							send(receiver) {
								if (this.request) return;
								var query = buildQueryString(this.data);
								var url = this.url + "/" + receiver.number + "?" + query;
								this.request = runtime.createScriptRequest(url);
								this.request.send(receiver);
							}
							cleanup() {
								if (this.request) this.request.cleanup();
							}
						}
						var getAgent = function(sender, useTLS) {
							return function(data, callback) {
								var url = "http" + (useTLS ? "s" : "") + "://" + (sender.host || sender.options.host) + sender.options.path;
								var request = runtime.createJSONPRequest(url, data);
								var receiver = runtime.ScriptReceivers.create(function(error, result) {
									ScriptReceivers.remove(receiver);
									request.cleanup();
									if (result && result.host) sender.host = result.host;
									if (callback) callback(error, result);
								});
								request.send(receiver);
							};
						};
						const jsonp_timeline = {
							name: "jsonp",
							getAgent
						};
						function getGenericURL(baseScheme, params, path) {
							var scheme = baseScheme + (params.useTLS ? "s" : "");
							var host = params.useTLS ? params.hostTLS : params.hostNonTLS;
							return scheme + "://" + host + path;
						}
						function getGenericPath(key, queryString) {
							return "/app/" + key + ("?protocol=" + defaults.PROTOCOL + "&client=js&version=" + defaults.VERSION + (queryString ? "&" + queryString : ""));
						}
						var ws = { getInitial: function(key, params) {
							return getGenericURL("ws", params, (params.httpPath || "") + getGenericPath(key, "flash=false"));
						} };
						var http = { getInitial: function(key, params) {
							return getGenericURL("http", params, (params.httpPath || "/pusher") + getGenericPath(key));
						} };
						var sockjs = {
							getInitial: function(key, params) {
								return getGenericURL("http", params, params.httpPath || "/pusher");
							},
							getPath: function(key, params) {
								return getGenericPath(key);
							}
						};
						class CallbackRegistry {
							constructor() {
								this._callbacks = {};
							}
							get(name) {
								return this._callbacks[prefix(name)];
							}
							add(name, callback, context) {
								var prefixedEventName = prefix(name);
								this._callbacks[prefixedEventName] = this._callbacks[prefixedEventName] || [];
								this._callbacks[prefixedEventName].push({
									fn: callback,
									context
								});
							}
							remove(name, callback, context) {
								if (!name && !callback && !context) {
									this._callbacks = {};
									return;
								}
								var names = name ? [prefix(name)] : keys(this._callbacks);
								if (callback || context) this.removeCallback(names, callback, context);
								else this.removeAllCallbacks(names);
							}
							removeCallback(names, callback, context) {
								apply(names, function(name) {
									this._callbacks[name] = filter(this._callbacks[name] || [], function(binding) {
										return callback && callback !== binding.fn || context && context !== binding.context;
									});
									if (this._callbacks[name].length === 0) delete this._callbacks[name];
								}, this);
							}
							removeAllCallbacks(names) {
								apply(names, function(name) {
									delete this._callbacks[name];
								}, this);
							}
						}
						function prefix(name) {
							return "_" + name;
						}
						class Dispatcher {
							constructor(failThrough) {
								this.callbacks = new CallbackRegistry();
								this.global_callbacks = [];
								this.failThrough = failThrough;
							}
							bind(eventName, callback, context) {
								this.callbacks.add(eventName, callback, context);
								return this;
							}
							bind_global(callback) {
								this.global_callbacks.push(callback);
								return this;
							}
							unbind(eventName, callback, context) {
								this.callbacks.remove(eventName, callback, context);
								return this;
							}
							unbind_global(callback) {
								if (!callback) {
									this.global_callbacks = [];
									return this;
								}
								this.global_callbacks = filter(this.global_callbacks || [], (c) => c !== callback);
								return this;
							}
							unbind_all() {
								this.unbind();
								this.unbind_global();
								return this;
							}
							emit(eventName, data, metadata) {
								for (var i = 0; i < this.global_callbacks.length; i++) this.global_callbacks[i](eventName, data);
								var callbacks = this.callbacks.get(eventName);
								var args = [];
								if (metadata) args.push(data, metadata);
								else if (data) args.push(data);
								if (callbacks && callbacks.length > 0) for (var i = 0; i < callbacks.length; i++) callbacks[i].fn.apply(callbacks[i].context || window, args);
								else if (this.failThrough) this.failThrough(eventName, data);
								return this;
							}
						}
						class TransportConnection extends Dispatcher {
							constructor(hooks, name, priority, key, options) {
								super();
								this.initialize = runtime.transportConnectionInitializer;
								this.hooks = hooks;
								this.name = name;
								this.priority = priority;
								this.key = key;
								this.options = options;
								this.state = "new";
								this.timeline = options.timeline;
								this.activityTimeout = options.activityTimeout;
								this.id = this.timeline.generateUniqueID();
							}
							handlesActivityChecks() {
								return Boolean(this.hooks.handlesActivityChecks);
							}
							supportsPing() {
								return Boolean(this.hooks.supportsPing);
							}
							connect() {
								if (this.socket || this.state !== "initialized") return false;
								var url = this.hooks.urls.getInitial(this.key, this.options);
								try {
									this.socket = this.hooks.getSocket(url, this.options);
								} catch (e) {
									util.defer(() => {
										this.onError(e);
										this.changeState("closed");
									});
									return false;
								}
								this.bindListeners();
								logger.debug("Connecting", {
									transport: this.name,
									url
								});
								this.changeState("connecting");
								return true;
							}
							close() {
								if (this.socket) {
									this.socket.close();
									return true;
								} else return false;
							}
							send(data) {
								if (this.state === "open") {
									util.defer(() => {
										if (this.socket) this.socket.send(data);
									});
									return true;
								} else return false;
							}
							ping() {
								if (this.state === "open" && this.supportsPing()) this.socket.ping();
							}
							onOpen() {
								if (this.hooks.beforeOpen) this.hooks.beforeOpen(this.socket, this.hooks.urls.getPath(this.key, this.options));
								this.changeState("open");
								this.socket.onopen = void 0;
							}
							onError(error) {
								this.emit("error", {
									type: "WebSocketError",
									error
								});
								this.timeline.error(this.buildTimelineMessage({ error: error.toString() }));
							}
							onClose(closeEvent) {
								if (closeEvent) this.changeState("closed", {
									code: closeEvent.code,
									reason: closeEvent.reason,
									wasClean: closeEvent.wasClean
								});
								else this.changeState("closed");
								this.unbindListeners();
								this.socket = void 0;
							}
							onMessage(message) {
								this.emit("message", message);
							}
							onActivity() {
								this.emit("activity");
							}
							bindListeners() {
								this.socket.onopen = () => {
									this.onOpen();
								};
								this.socket.onerror = (error) => {
									this.onError(error);
								};
								this.socket.onclose = (closeEvent) => {
									this.onClose(closeEvent);
								};
								this.socket.onmessage = (message) => {
									this.onMessage(message);
								};
								if (this.supportsPing()) this.socket.onactivity = () => {
									this.onActivity();
								};
							}
							unbindListeners() {
								if (this.socket) {
									this.socket.onopen = void 0;
									this.socket.onerror = void 0;
									this.socket.onclose = void 0;
									this.socket.onmessage = void 0;
									if (this.supportsPing()) this.socket.onactivity = void 0;
								}
							}
							changeState(state, params) {
								this.state = state;
								this.timeline.info(this.buildTimelineMessage({
									state,
									params
								}));
								this.emit(state, params);
							}
							buildTimelineMessage(message) {
								return extend({ cid: this.id }, message);
							}
						}
						class Transport {
							constructor(hooks) {
								this.hooks = hooks;
							}
							isSupported(environment) {
								return this.hooks.isSupported(environment);
							}
							createConnection(name, priority, key, options) {
								return new TransportConnection(this.hooks, name, priority, key, options);
							}
						}
						var WSTransport = new Transport({
							urls: ws,
							handlesActivityChecks: false,
							supportsPing: false,
							isInitialized: function() {
								return Boolean(runtime.getWebSocketAPI());
							},
							isSupported: function() {
								return Boolean(runtime.getWebSocketAPI());
							},
							getSocket: function(url) {
								return runtime.createWebSocket(url);
							}
						});
						var httpConfiguration = {
							urls: http,
							handlesActivityChecks: false,
							supportsPing: true,
							isInitialized: function() {
								return true;
							}
						};
						var streamingConfiguration = extend({ getSocket: function(url) {
							return runtime.HTTPFactory.createStreamingSocket(url);
						} }, httpConfiguration);
						var pollingConfiguration = extend({ getSocket: function(url) {
							return runtime.HTTPFactory.createPollingSocket(url);
						} }, httpConfiguration);
						var xhrConfiguration = { isSupported: function() {
							return runtime.isXHRSupported();
						} };
						const transports = {
							ws: WSTransport,
							xhr_streaming: new Transport(extend({}, streamingConfiguration, xhrConfiguration)),
							xhr_polling: new Transport(extend({}, pollingConfiguration, xhrConfiguration))
						};
						var SockJSTransport = new Transport({
							file: "sockjs",
							urls: sockjs,
							handlesActivityChecks: true,
							supportsPing: false,
							isSupported: function() {
								return true;
							},
							isInitialized: function() {
								return window.SockJS !== void 0;
							},
							getSocket: function(url, options) {
								return new window.SockJS(url, null, {
									js_path: Dependencies.getPath("sockjs", { useTLS: options.useTLS }),
									ignore_null_origin: options.ignoreNullOrigin
								});
							},
							beforeOpen: function(socket, path) {
								socket.send(JSON.stringify({ path }));
							}
						});
						var xdrConfiguration = { isSupported: function(environment) {
							return runtime.isXDRSupported(environment.useTLS);
						} };
						var XDRStreamingTransport = new Transport(extend({}, streamingConfiguration, xdrConfiguration));
						var XDRPollingTransport = new Transport(extend({}, pollingConfiguration, xdrConfiguration));
						transports.xdr_streaming = XDRStreamingTransport;
						transports.xdr_polling = XDRPollingTransport;
						transports.sockjs = SockJSTransport;
						const transports_transports = transports;
						class NetInfo extends Dispatcher {
							constructor() {
								super();
								var self = this;
								if (window.addEventListener !== void 0) {
									window.addEventListener("online", function() {
										self.emit("online");
									}, false);
									window.addEventListener("offline", function() {
										self.emit("offline");
									}, false);
								}
							}
							isOnline() {
								if (window.navigator.onLine === void 0) return true;
								else return window.navigator.onLine;
							}
						}
						var Network = new NetInfo();
						class AssistantToTheTransportManager {
							constructor(manager, transport, options) {
								this.manager = manager;
								this.transport = transport;
								this.minPingDelay = options.minPingDelay;
								this.maxPingDelay = options.maxPingDelay;
								this.pingDelay = void 0;
							}
							createConnection(name, priority, key, options) {
								options = extend({}, options, { activityTimeout: this.pingDelay });
								var connection = this.transport.createConnection(name, priority, key, options);
								var openTimestamp = null;
								var onOpen = function() {
									connection.unbind("open", onOpen);
									connection.bind("closed", onClosed);
									openTimestamp = util.now();
								};
								var onClosed = (closeEvent) => {
									connection.unbind("closed", onClosed);
									if (closeEvent.code === 1002 || closeEvent.code === 1003) this.manager.reportDeath();
									else if (!closeEvent.wasClean && openTimestamp) {
										var lifespan = util.now() - openTimestamp;
										if (lifespan < 2 * this.maxPingDelay) {
											this.manager.reportDeath();
											this.pingDelay = Math.max(lifespan / 2, this.minPingDelay);
										}
									}
								};
								connection.bind("open", onOpen);
								return connection;
							}
							isSupported(environment) {
								return this.manager.isAlive() && this.transport.isSupported(environment);
							}
						}
						const Protocol = {
							decodeMessage: function(messageEvent) {
								try {
									var messageData = JSON.parse(messageEvent.data);
									var pusherEventData = messageData.data;
									if (typeof pusherEventData === "string") try {
										pusherEventData = JSON.parse(messageData.data);
									} catch (e) {}
									var pusherEvent = {
										event: messageData.event,
										channel: messageData.channel,
										data: pusherEventData
									};
									if (messageData.user_id) pusherEvent.user_id = messageData.user_id;
									return pusherEvent;
								} catch (e) {
									throw {
										type: "MessageParseError",
										error: e,
										data: messageEvent.data
									};
								}
							},
							encodeMessage: function(event) {
								return JSON.stringify(event);
							},
							processHandshake: function(messageEvent) {
								var message = Protocol.decodeMessage(messageEvent);
								if (message.event === "pusher:connection_established") {
									if (!message.data.activity_timeout) throw "No activity timeout specified in handshake";
									return {
										action: "connected",
										id: message.data.socket_id,
										activityTimeout: message.data.activity_timeout * 1e3
									};
								} else if (message.event === "pusher:error") return {
									action: this.getCloseAction(message.data),
									error: this.getCloseError(message.data)
								};
								else throw "Invalid handshake";
							},
							getCloseAction: function(closeEvent) {
								if (closeEvent.code < 4e3) if (closeEvent.code >= 1002 && closeEvent.code <= 1004) return "backoff";
								else return null;
								else if (closeEvent.code === 4e3) return "tls_only";
								else if (closeEvent.code < 4100) return "refused";
								else if (closeEvent.code < 4200) return "backoff";
								else if (closeEvent.code < 4300) return "retry";
								else return "refused";
							},
							getCloseError: function(closeEvent) {
								if (closeEvent.code !== 1e3 && closeEvent.code !== 1001) return {
									type: "PusherError",
									data: {
										code: closeEvent.code,
										message: closeEvent.reason || closeEvent.message
									}
								};
								else return null;
							}
						};
						const protocol = Protocol;
						class Connection extends Dispatcher {
							constructor(id, transport) {
								super();
								this.id = id;
								this.transport = transport;
								this.activityTimeout = transport.activityTimeout;
								this.bindListeners();
							}
							handlesActivityChecks() {
								return this.transport.handlesActivityChecks();
							}
							send(data) {
								return this.transport.send(data);
							}
							send_event(name, data, channel) {
								var event = {
									event: name,
									data
								};
								if (channel) event.channel = channel;
								logger.debug("Event sent", event);
								return this.send(protocol.encodeMessage(event));
							}
							ping() {
								if (this.transport.supportsPing()) this.transport.ping();
								else this.send_event("pusher:ping", {});
							}
							close() {
								this.transport.close();
							}
							bindListeners() {
								var listeners = {
									message: (messageEvent) => {
										var pusherEvent;
										try {
											pusherEvent = protocol.decodeMessage(messageEvent);
										} catch (e) {
											this.emit("error", {
												type: "MessageParseError",
												error: e,
												data: messageEvent.data
											});
										}
										if (pusherEvent !== void 0) {
											logger.debug("Event recd", pusherEvent);
											switch (pusherEvent.event) {
												case "pusher:error":
													this.emit("error", {
														type: "PusherError",
														data: pusherEvent.data
													});
													break;
												case "pusher:ping":
													this.emit("ping");
													break;
												case "pusher:pong":
													this.emit("pong");
													break;
											}
											this.emit("message", pusherEvent);
										}
									},
									activity: () => {
										this.emit("activity");
									},
									error: (error) => {
										this.emit("error", error);
									},
									closed: (closeEvent) => {
										unbindListeners();
										if (closeEvent && closeEvent.code) this.handleCloseEvent(closeEvent);
										this.transport = null;
										this.emit("closed");
									}
								};
								var unbindListeners = () => {
									objectApply(listeners, (listener, event) => {
										this.transport.unbind(event, listener);
									});
								};
								objectApply(listeners, (listener, event) => {
									this.transport.bind(event, listener);
								});
							}
							handleCloseEvent(closeEvent) {
								var action = protocol.getCloseAction(closeEvent);
								var error = protocol.getCloseError(closeEvent);
								if (error) this.emit("error", error);
								if (action) this.emit(action, {
									action,
									error
								});
							}
						}
						class Handshake {
							constructor(transport, callback) {
								this.transport = transport;
								this.callback = callback;
								this.bindListeners();
							}
							close() {
								this.unbindListeners();
								this.transport.close();
							}
							bindListeners() {
								this.onMessage = (m) => {
									this.unbindListeners();
									var result;
									try {
										result = protocol.processHandshake(m);
									} catch (e) {
										this.finish("error", { error: e });
										this.transport.close();
										return;
									}
									if (result.action === "connected") this.finish("connected", {
										connection: new Connection(result.id, this.transport),
										activityTimeout: result.activityTimeout
									});
									else {
										this.finish(result.action, { error: result.error });
										this.transport.close();
									}
								};
								this.onClosed = (closeEvent) => {
									this.unbindListeners();
									var action = protocol.getCloseAction(closeEvent) || "backoff";
									var error = protocol.getCloseError(closeEvent);
									this.finish(action, { error });
								};
								this.transport.bind("message", this.onMessage);
								this.transport.bind("closed", this.onClosed);
							}
							unbindListeners() {
								this.transport.unbind("message", this.onMessage);
								this.transport.unbind("closed", this.onClosed);
							}
							finish(action, params) {
								this.callback(extend({
									transport: this.transport,
									action
								}, params));
							}
						}
						class TimelineSender {
							constructor(timeline, options) {
								this.timeline = timeline;
								this.options = options || {};
							}
							send(useTLS, callback) {
								if (this.timeline.isEmpty()) return;
								this.timeline.send(runtime.TimelineTransport.getAgent(this, useTLS), callback);
							}
						}
						class Channel extends Dispatcher {
							constructor(name, pusher) {
								super(function(event, data) {
									logger.debug("No callbacks on " + name + " for " + event);
								});
								this.name = name;
								this.pusher = pusher;
								this.subscribed = false;
								this.subscriptionPending = false;
								this.subscriptionCancelled = false;
							}
							authorize(socketId, callback) {
								return callback(null, { auth: "" });
							}
							trigger(event, data) {
								if (event.indexOf("client-") !== 0) throw new BadEventName("Event '" + event + "' does not start with 'client-'");
								if (!this.subscribed) {
									var suffix = url_store.buildLogSuffix("triggeringClientEvents");
									logger.warn(`Client event triggered before channel 'subscription_succeeded' event . ${suffix}`);
								}
								return this.pusher.send_event(event, data, this.name);
							}
							disconnect() {
								this.subscribed = false;
								this.subscriptionPending = false;
							}
							handleEvent(event) {
								var eventName = event.event;
								var data = event.data;
								if (eventName === "pusher_internal:subscription_succeeded") this.handleSubscriptionSucceededEvent(event);
								else if (eventName === "pusher_internal:subscription_count") this.handleSubscriptionCountEvent(event);
								else if (eventName.indexOf("pusher_internal:") !== 0) this.emit(eventName, data, {});
							}
							handleSubscriptionSucceededEvent(event) {
								this.subscriptionPending = false;
								this.subscribed = true;
								if (this.subscriptionCancelled) this.pusher.unsubscribe(this.name);
								else this.emit("pusher:subscription_succeeded", event.data);
							}
							handleSubscriptionCountEvent(event) {
								if (event.data.subscription_count) this.subscriptionCount = event.data.subscription_count;
								this.emit("pusher:subscription_count", event.data);
							}
							subscribe() {
								if (this.subscribed) return;
								this.subscriptionPending = true;
								this.subscriptionCancelled = false;
								this.authorize(this.pusher.connection.socket_id, (error, data) => {
									if (error) {
										this.subscriptionPending = false;
										logger.error(error.toString());
										this.emit("pusher:subscription_error", Object.assign({}, {
											type: "AuthError",
											error: error.message
										}, error instanceof HTTPAuthError ? { status: error.status } : {}));
									} else this.pusher.send_event("pusher:subscribe", {
										auth: data.auth,
										channel_data: data.channel_data,
										channel: this.name
									});
								});
							}
							unsubscribe() {
								this.subscribed = false;
								this.pusher.send_event("pusher:unsubscribe", { channel: this.name });
							}
							cancelSubscription() {
								this.subscriptionCancelled = true;
							}
							reinstateSubscription() {
								this.subscriptionCancelled = false;
							}
						}
						class PrivateChannel extends Channel {
							authorize(socketId, callback) {
								return this.pusher.config.channelAuthorizer({
									channelName: this.name,
									socketId
								}, callback);
							}
						}
						class Members {
							constructor() {
								this.reset();
							}
							get(id) {
								if (Object.prototype.hasOwnProperty.call(this.members, id)) return {
									id,
									info: this.members[id]
								};
								else return null;
							}
							each(callback) {
								objectApply(this.members, (member, id) => {
									callback(this.get(id));
								});
							}
							setMyID(id) {
								this.myID = id;
							}
							onSubscription(subscriptionData) {
								this.members = subscriptionData.presence.hash;
								this.count = subscriptionData.presence.count;
								this.me = this.get(this.myID);
							}
							addMember(memberData) {
								if (this.get(memberData.user_id) === null) this.count++;
								this.members[memberData.user_id] = memberData.user_info;
								return this.get(memberData.user_id);
							}
							removeMember(memberData) {
								var member = this.get(memberData.user_id);
								if (member) {
									delete this.members[memberData.user_id];
									this.count--;
								}
								return member;
							}
							reset() {
								this.members = {};
								this.count = 0;
								this.myID = null;
								this.me = null;
							}
						}
						var __awaiter = function(thisArg, _arguments, P, generator) {
							function adopt(value) {
								return value instanceof P ? value : new P(function(resolve) {
									resolve(value);
								});
							}
							return new (P || (P = Promise))(function(resolve, reject) {
								function fulfilled(value) {
									try {
										step(generator.next(value));
									} catch (e) {
										reject(e);
									}
								}
								function rejected(value) {
									try {
										step(generator["throw"](value));
									} catch (e) {
										reject(e);
									}
								}
								function step(result) {
									result.done ? resolve(result.value) : adopt(result.value).then(fulfilled, rejected);
								}
								step((generator = generator.apply(thisArg, _arguments || [])).next());
							});
						};
						class PresenceChannel extends PrivateChannel {
							constructor(name, pusher) {
								super(name, pusher);
								this.members = new Members();
							}
							authorize(socketId, callback) {
								super.authorize(socketId, (error, authData) => __awaiter(this, void 0, void 0, function* () {
									if (!error) {
										authData = authData;
										if (authData.channel_data != null) {
											var channelData = JSON.parse(authData.channel_data);
											this.members.setMyID(channelData.user_id);
										} else {
											yield this.pusher.user.signinDonePromise;
											if (this.pusher.user.user_data != null) this.members.setMyID(this.pusher.user.user_data.id);
											else {
												let suffix = url_store.buildLogSuffix("authorizationEndpoint");
												logger.error(`Invalid auth response for channel '${this.name}', expected 'channel_data' field. ${suffix}, or the user should be signed in.`);
												callback("Invalid auth response");
												return;
											}
										}
									}
									callback(error, authData);
								}));
							}
							handleEvent(event) {
								var eventName = event.event;
								if (eventName.indexOf("pusher_internal:") === 0) this.handleInternalEvent(event);
								else {
									var data = event.data;
									var metadata = {};
									if (event.user_id) metadata.user_id = event.user_id;
									this.emit(eventName, data, metadata);
								}
							}
							handleInternalEvent(event) {
								var eventName = event.event;
								var data = event.data;
								switch (eventName) {
									case "pusher_internal:subscription_succeeded":
										this.handleSubscriptionSucceededEvent(event);
										break;
									case "pusher_internal:subscription_count":
										this.handleSubscriptionCountEvent(event);
										break;
									case "pusher_internal:member_added":
										var addedMember = this.members.addMember(data);
										this.emit("pusher:member_added", addedMember);
										break;
									case "pusher_internal:member_removed":
										var removedMember = this.members.removeMember(data);
										if (removedMember) this.emit("pusher:member_removed", removedMember);
										break;
								}
							}
							handleSubscriptionSucceededEvent(event) {
								this.subscriptionPending = false;
								this.subscribed = true;
								if (this.subscriptionCancelled) this.pusher.unsubscribe(this.name);
								else {
									this.members.onSubscription(event.data);
									this.emit("pusher:subscription_succeeded", this.members);
								}
							}
							disconnect() {
								this.members.reset();
								super.disconnect();
							}
						}
						var utf8 = __webpack_require__(978);
						var base64 = __webpack_require__(594);
						class EncryptedChannel extends PrivateChannel {
							constructor(name, pusher, nacl) {
								super(name, pusher);
								this.key = null;
								this.nacl = nacl;
							}
							authorize(socketId, callback) {
								super.authorize(socketId, (error, authData) => {
									if (error) {
										callback(error, authData);
										return;
									}
									let sharedSecret = authData["shared_secret"];
									if (!sharedSecret) {
										callback(/* @__PURE__ */ new Error(`No shared_secret key in auth payload for encrypted channel: ${this.name}`), null);
										return;
									}
									this.key = (0, base64.decode)(sharedSecret);
									delete authData["shared_secret"];
									callback(null, authData);
								});
							}
							trigger(event, data) {
								throw new UnsupportedFeature("Client events are not currently supported for encrypted channels");
							}
							handleEvent(event) {
								var eventName = event.event;
								var data = event.data;
								if (eventName.indexOf("pusher_internal:") === 0 || eventName.indexOf("pusher:") === 0) {
									super.handleEvent(event);
									return;
								}
								this.handleEncryptedEvent(eventName, data);
							}
							handleEncryptedEvent(event, data) {
								if (!this.key) {
									logger.debug("Received encrypted event before key has been retrieved from the authEndpoint");
									return;
								}
								if (!data.ciphertext || !data.nonce) {
									logger.error("Unexpected format for encrypted event, expected object with `ciphertext` and `nonce` fields, got: " + data);
									return;
								}
								let cipherText = (0, base64.decode)(data.ciphertext);
								if (cipherText.length < this.nacl.secretbox.overheadLength) {
									logger.error(`Expected encrypted event ciphertext length to be ${this.nacl.secretbox.overheadLength}, got: ${cipherText.length}`);
									return;
								}
								let nonce = (0, base64.decode)(data.nonce);
								if (nonce.length < this.nacl.secretbox.nonceLength) {
									logger.error(`Expected encrypted event nonce length to be ${this.nacl.secretbox.nonceLength}, got: ${nonce.length}`);
									return;
								}
								let bytes = this.nacl.secretbox.open(cipherText, nonce, this.key);
								if (bytes === null) {
									logger.debug("Failed to decrypt an event, probably because it was encrypted with a different key. Fetching a new key from the authEndpoint...");
									this.authorize(this.pusher.connection.socket_id, (error, authData) => {
										if (error) {
											logger.error(`Failed to make a request to the authEndpoint: ${authData}. Unable to fetch new key, so dropping encrypted event`);
											return;
										}
										bytes = this.nacl.secretbox.open(cipherText, nonce, this.key);
										if (bytes === null) {
											logger.error(`Failed to decrypt event with new key. Dropping encrypted event`);
											return;
										}
										this.emit(event, this.getDataToEmit(bytes));
									});
									return;
								}
								this.emit(event, this.getDataToEmit(bytes));
							}
							getDataToEmit(bytes) {
								let raw = (0, utf8.D4)(bytes);
								try {
									return JSON.parse(raw);
								} catch (_a) {
									return raw;
								}
							}
						}
						class ConnectionManager extends Dispatcher {
							constructor(key, options) {
								super();
								this.state = "initialized";
								this.connection = null;
								this.key = key;
								this.options = options;
								this.timeline = this.options.timeline;
								this.usingTLS = this.options.useTLS;
								this.errorCallbacks = this.buildErrorCallbacks();
								this.connectionCallbacks = this.buildConnectionCallbacks(this.errorCallbacks);
								this.handshakeCallbacks = this.buildHandshakeCallbacks(this.errorCallbacks);
								var Network = runtime.getNetwork();
								Network.bind("online", () => {
									this.timeline.info({ netinfo: "online" });
									if (this.state === "connecting" || this.state === "unavailable") this.retryIn(0);
								});
								Network.bind("offline", () => {
									this.timeline.info({ netinfo: "offline" });
									if (this.connection) this.sendActivityCheck();
								});
								this.updateStrategy();
							}
							switchCluster(key) {
								this.key = key;
								this.updateStrategy();
								this.retryIn(0);
							}
							connect() {
								if (this.connection || this.runner) return;
								if (!this.strategy.isSupported()) {
									this.updateState("failed");
									return;
								}
								this.updateState("connecting");
								this.startConnecting();
								this.setUnavailableTimer();
							}
							send(data) {
								if (this.connection) return this.connection.send(data);
								else return false;
							}
							send_event(name, data, channel) {
								if (this.connection) return this.connection.send_event(name, data, channel);
								else return false;
							}
							disconnect() {
								this.disconnectInternally();
								this.updateState("disconnected");
							}
							isUsingTLS() {
								return this.usingTLS;
							}
							startConnecting() {
								var callback = (error, handshake) => {
									if (error) this.runner = this.strategy.connect(0, callback);
									else if (handshake.action === "error") {
										this.emit("error", {
											type: "HandshakeError",
											error: handshake.error
										});
										this.timeline.error({ handshakeError: handshake.error });
									} else {
										this.abortConnecting();
										this.handshakeCallbacks[handshake.action](handshake);
									}
								};
								this.runner = this.strategy.connect(0, callback);
							}
							abortConnecting() {
								if (this.runner) {
									this.runner.abort();
									this.runner = null;
								}
							}
							disconnectInternally() {
								this.abortConnecting();
								this.clearRetryTimer();
								this.clearUnavailableTimer();
								if (this.connection) this.abandonConnection().close();
							}
							updateStrategy() {
								this.strategy = this.options.getStrategy({
									key: this.key,
									timeline: this.timeline,
									useTLS: this.usingTLS
								});
							}
							retryIn(delay) {
								this.timeline.info({
									action: "retry",
									delay
								});
								if (delay > 0) this.emit("connecting_in", Math.round(delay / 1e3));
								this.retryTimer = new OneOffTimer(delay || 0, () => {
									this.disconnectInternally();
									this.connect();
								});
							}
							clearRetryTimer() {
								if (this.retryTimer) {
									this.retryTimer.ensureAborted();
									this.retryTimer = null;
								}
							}
							setUnavailableTimer() {
								this.unavailableTimer = new OneOffTimer(this.options.unavailableTimeout, () => {
									this.updateState("unavailable");
								});
							}
							clearUnavailableTimer() {
								if (this.unavailableTimer) this.unavailableTimer.ensureAborted();
							}
							sendActivityCheck() {
								this.stopActivityCheck();
								this.connection.ping();
								this.activityTimer = new OneOffTimer(this.options.pongTimeout, () => {
									this.timeline.error({ pong_timed_out: this.options.pongTimeout });
									this.retryIn(0);
								});
							}
							resetActivityCheck() {
								this.stopActivityCheck();
								if (this.connection && !this.connection.handlesActivityChecks()) this.activityTimer = new OneOffTimer(this.activityTimeout, () => {
									this.sendActivityCheck();
								});
							}
							stopActivityCheck() {
								if (this.activityTimer) this.activityTimer.ensureAborted();
							}
							buildConnectionCallbacks(errorCallbacks) {
								return extend({}, errorCallbacks, {
									message: (message) => {
										this.resetActivityCheck();
										this.emit("message", message);
									},
									ping: () => {
										this.send_event("pusher:pong", {});
									},
									activity: () => {
										this.resetActivityCheck();
									},
									error: (error) => {
										this.emit("error", error);
									},
									closed: () => {
										this.abandonConnection();
										if (this.shouldRetry()) this.retryIn(1e3);
									}
								});
							}
							buildHandshakeCallbacks(errorCallbacks) {
								return extend({}, errorCallbacks, { connected: (handshake) => {
									this.activityTimeout = Math.min(this.options.activityTimeout, handshake.activityTimeout, handshake.connection.activityTimeout || Infinity);
									this.clearUnavailableTimer();
									this.setConnection(handshake.connection);
									this.socket_id = this.connection.id;
									this.updateState("connected", { socket_id: this.socket_id });
								} });
							}
							buildErrorCallbacks() {
								let withErrorEmitted = (callback) => {
									return (result) => {
										if (result.error) this.emit("error", {
											type: "WebSocketError",
											error: result.error
										});
										callback(result);
									};
								};
								return {
									tls_only: withErrorEmitted(() => {
										this.usingTLS = true;
										this.updateStrategy();
										this.retryIn(0);
									}),
									refused: withErrorEmitted(() => {
										this.disconnect();
									}),
									backoff: withErrorEmitted(() => {
										this.retryIn(1e3);
									}),
									retry: withErrorEmitted(() => {
										this.retryIn(0);
									})
								};
							}
							setConnection(connection) {
								this.connection = connection;
								for (var event in this.connectionCallbacks) this.connection.bind(event, this.connectionCallbacks[event]);
								this.resetActivityCheck();
							}
							abandonConnection() {
								if (!this.connection) return;
								this.stopActivityCheck();
								for (var event in this.connectionCallbacks) this.connection.unbind(event, this.connectionCallbacks[event]);
								var connection = this.connection;
								this.connection = null;
								return connection;
							}
							updateState(newState, data) {
								var previousState = this.state;
								this.state = newState;
								if (previousState !== newState) {
									var newStateDescription = newState;
									if (newStateDescription === "connected") newStateDescription += " with new socket ID " + data.socket_id;
									logger.debug("State changed", previousState + " -> " + newStateDescription);
									this.timeline.info({
										state: newState,
										params: data
									});
									this.emit("state_change", {
										previous: previousState,
										current: newState
									});
									this.emit(newState, data);
								}
							}
							shouldRetry() {
								return this.state === "connecting" || this.state === "connected";
							}
						}
						class Channels {
							constructor() {
								this.channels = {};
							}
							add(name, pusher) {
								if (!this.channels[name]) this.channels[name] = createChannel(name, pusher);
								return this.channels[name];
							}
							all() {
								return values(this.channels);
							}
							find(name) {
								return this.channels[name];
							}
							remove(name) {
								var channel = this.channels[name];
								delete this.channels[name];
								return channel;
							}
							disconnect() {
								objectApply(this.channels, function(channel) {
									channel.disconnect();
								});
							}
						}
						function createChannel(name, pusher) {
							if (name.indexOf("private-encrypted-") === 0) {
								if (pusher.config.nacl) return factory.createEncryptedChannel(name, pusher, pusher.config.nacl);
								throw new UnsupportedFeature(`Tried to subscribe to a private-encrypted- channel but no nacl implementation available. ${url_store.buildLogSuffix("encryptedChannelSupport")}`);
							} else if (name.indexOf("private-") === 0) return factory.createPrivateChannel(name, pusher);
							else if (name.indexOf("presence-") === 0) return factory.createPresenceChannel(name, pusher);
							else if (name.indexOf("#") === 0) throw new BadChannelName("Cannot create a channel with name \"" + name + "\".");
							else return factory.createChannel(name, pusher);
						}
						const factory = {
							createChannels() {
								return new Channels();
							},
							createConnectionManager(key, options) {
								return new ConnectionManager(key, options);
							},
							createChannel(name, pusher) {
								return new Channel(name, pusher);
							},
							createPrivateChannel(name, pusher) {
								return new PrivateChannel(name, pusher);
							},
							createPresenceChannel(name, pusher) {
								return new PresenceChannel(name, pusher);
							},
							createEncryptedChannel(name, pusher, nacl) {
								return new EncryptedChannel(name, pusher, nacl);
							},
							createTimelineSender(timeline, options) {
								return new TimelineSender(timeline, options);
							},
							createHandshake(transport, callback) {
								return new Handshake(transport, callback);
							},
							createAssistantToTheTransportManager(manager, transport, options) {
								return new AssistantToTheTransportManager(manager, transport, options);
							}
						};
						class TransportManager {
							constructor(options) {
								this.options = options || {};
								this.livesLeft = this.options.lives || Infinity;
							}
							getAssistant(transport) {
								return factory.createAssistantToTheTransportManager(this, transport, {
									minPingDelay: this.options.minPingDelay,
									maxPingDelay: this.options.maxPingDelay
								});
							}
							isAlive() {
								return this.livesLeft > 0;
							}
							reportDeath() {
								this.livesLeft -= 1;
							}
						}
						class SequentialStrategy {
							constructor(strategies, options) {
								this.strategies = strategies;
								this.loop = Boolean(options.loop);
								this.failFast = Boolean(options.failFast);
								this.timeout = options.timeout;
								this.timeoutLimit = options.timeoutLimit;
							}
							isSupported() {
								return any(this.strategies, util.method("isSupported"));
							}
							connect(minPriority, callback) {
								var strategies = this.strategies;
								var current = 0;
								var timeout = this.timeout;
								var runner = null;
								var tryNextStrategy = (error, handshake) => {
									if (handshake) callback(null, handshake);
									else {
										current = current + 1;
										if (this.loop) current = current % strategies.length;
										if (current < strategies.length) {
											if (timeout) {
												timeout = timeout * 2;
												if (this.timeoutLimit) timeout = Math.min(timeout, this.timeoutLimit);
											}
											runner = this.tryStrategy(strategies[current], minPriority, {
												timeout,
												failFast: this.failFast
											}, tryNextStrategy);
										} else callback(true);
									}
								};
								runner = this.tryStrategy(strategies[current], minPriority, {
									timeout,
									failFast: this.failFast
								}, tryNextStrategy);
								return {
									abort: function() {
										runner.abort();
									},
									forceMinPriority: function(p) {
										minPriority = p;
										if (runner) runner.forceMinPriority(p);
									}
								};
							}
							tryStrategy(strategy, minPriority, options, callback) {
								var timer = null;
								var runner = null;
								if (options.timeout > 0) timer = new OneOffTimer(options.timeout, function() {
									runner.abort();
									callback(true);
								});
								runner = strategy.connect(minPriority, function(error, handshake) {
									if (error && timer && timer.isRunning() && !options.failFast) return;
									if (timer) timer.ensureAborted();
									callback(error, handshake);
								});
								return {
									abort: function() {
										if (timer) timer.ensureAborted();
										runner.abort();
									},
									forceMinPriority: function(p) {
										runner.forceMinPriority(p);
									}
								};
							}
						}
						class BestConnectedEverStrategy {
							constructor(strategies) {
								this.strategies = strategies;
							}
							isSupported() {
								return any(this.strategies, util.method("isSupported"));
							}
							connect(minPriority, callback) {
								return connect(this.strategies, minPriority, function(i, runners) {
									return function(error, handshake) {
										runners[i].error = error;
										if (error) {
											if (allRunnersFailed(runners)) callback(true);
											return;
										}
										apply(runners, function(runner) {
											runner.forceMinPriority(handshake.transport.priority);
										});
										callback(null, handshake);
									};
								});
							}
						}
						function connect(strategies, minPriority, callbackBuilder) {
							var runners = map(strategies, function(strategy, i, _, rs) {
								return strategy.connect(minPriority, callbackBuilder(i, rs));
							});
							return {
								abort: function() {
									apply(runners, abortRunner);
								},
								forceMinPriority: function(p) {
									apply(runners, function(runner) {
										runner.forceMinPriority(p);
									});
								}
							};
						}
						function allRunnersFailed(runners) {
							return collections_all(runners, function(runner) {
								return Boolean(runner.error);
							});
						}
						function abortRunner(runner) {
							if (!runner.error && !runner.aborted) {
								runner.abort();
								runner.aborted = true;
							}
						}
						class WebSocketPrioritizedCachedStrategy {
							constructor(strategy, transports, options) {
								this.strategy = strategy;
								this.transports = transports;
								this.ttl = options.ttl || 1800 * 1e3;
								this.usingTLS = options.useTLS;
								this.timeline = options.timeline;
							}
							isSupported() {
								return this.strategy.isSupported();
							}
							connect(minPriority, callback) {
								var usingTLS = this.usingTLS;
								var info = fetchTransportCache(usingTLS);
								var cacheSkipCount = info && info.cacheSkipCount ? info.cacheSkipCount : 0;
								var strategies = [this.strategy];
								if (info && info.timestamp + this.ttl >= util.now()) {
									var transport = this.transports[info.transport];
									if (transport) if (["ws", "wss"].includes(info.transport) || cacheSkipCount > 3) {
										this.timeline.info({
											cached: true,
											transport: info.transport,
											latency: info.latency
										});
										strategies.push(new SequentialStrategy([transport], {
											timeout: info.latency * 2 + 1e3,
											failFast: true
										}));
									} else cacheSkipCount++;
								}
								var startTimestamp = util.now();
								var runner = strategies.pop().connect(minPriority, function cb(error, handshake) {
									if (error) {
										flushTransportCache(usingTLS);
										if (strategies.length > 0) {
											startTimestamp = util.now();
											runner = strategies.pop().connect(minPriority, cb);
										} else callback(error);
									} else {
										storeTransportCache(usingTLS, handshake.transport.name, util.now() - startTimestamp, cacheSkipCount);
										callback(null, handshake);
									}
								});
								return {
									abort: function() {
										runner.abort();
									},
									forceMinPriority: function(p) {
										minPriority = p;
										if (runner) runner.forceMinPriority(p);
									}
								};
							}
						}
						function getTransportCacheKey(usingTLS) {
							return "pusherTransport" + (usingTLS ? "TLS" : "NonTLS");
						}
						function fetchTransportCache(usingTLS) {
							var storage = runtime.getLocalStorage();
							if (storage) try {
								var serializedCache = storage[getTransportCacheKey(usingTLS)];
								if (serializedCache) return JSON.parse(serializedCache);
							} catch (e) {
								flushTransportCache(usingTLS);
							}
							return null;
						}
						function storeTransportCache(usingTLS, transport, latency, cacheSkipCount) {
							var storage = runtime.getLocalStorage();
							if (storage) try {
								storage[getTransportCacheKey(usingTLS)] = safeJSONStringify({
									timestamp: util.now(),
									transport,
									latency,
									cacheSkipCount
								});
							} catch (e) {}
						}
						function flushTransportCache(usingTLS) {
							var storage = runtime.getLocalStorage();
							if (storage) try {
								delete storage[getTransportCacheKey(usingTLS)];
							} catch (e) {}
						}
						class DelayedStrategy {
							constructor(strategy, { delay: number }) {
								this.strategy = strategy;
								this.options = { delay: number };
							}
							isSupported() {
								return this.strategy.isSupported();
							}
							connect(minPriority, callback) {
								var strategy = this.strategy;
								var runner;
								var timer = new OneOffTimer(this.options.delay, function() {
									runner = strategy.connect(minPriority, callback);
								});
								return {
									abort: function() {
										timer.ensureAborted();
										if (runner) runner.abort();
									},
									forceMinPriority: function(p) {
										minPriority = p;
										if (runner) runner.forceMinPriority(p);
									}
								};
							}
						}
						class IfStrategy {
							constructor(test, trueBranch, falseBranch) {
								this.test = test;
								this.trueBranch = trueBranch;
								this.falseBranch = falseBranch;
							}
							isSupported() {
								return (this.test() ? this.trueBranch : this.falseBranch).isSupported();
							}
							connect(minPriority, callback) {
								return (this.test() ? this.trueBranch : this.falseBranch).connect(minPriority, callback);
							}
						}
						class FirstConnectedStrategy {
							constructor(strategy) {
								this.strategy = strategy;
							}
							isSupported() {
								return this.strategy.isSupported();
							}
							connect(minPriority, callback) {
								var runner = this.strategy.connect(minPriority, function(error, handshake) {
									if (handshake) runner.abort();
									callback(error, handshake);
								});
								return runner;
							}
						}
						function testSupportsStrategy(strategy) {
							return function() {
								return strategy.isSupported();
							};
						}
						var getDefaultStrategy = function(config, baseOptions, defineTransport) {
							var definedTransports = {};
							function defineTransportStrategy(name, type, priority, options, manager) {
								var transport = defineTransport(config, name, type, priority, options, manager);
								definedTransports[name] = transport;
								return transport;
							}
							var ws_options = Object.assign({}, baseOptions, {
								hostNonTLS: config.wsHost + ":" + config.wsPort,
								hostTLS: config.wsHost + ":" + config.wssPort,
								httpPath: config.wsPath
							});
							var wss_options = Object.assign({}, ws_options, { useTLS: true });
							var sockjs_options = Object.assign({}, baseOptions, {
								hostNonTLS: config.httpHost + ":" + config.httpPort,
								hostTLS: config.httpHost + ":" + config.httpsPort,
								httpPath: config.httpPath
							});
							var timeouts = {
								loop: true,
								timeout: 15e3,
								timeoutLimit: 6e4
							};
							var ws_manager = new TransportManager({
								minPingDelay: 1e4,
								maxPingDelay: config.activityTimeout
							});
							var streaming_manager = new TransportManager({
								lives: 2,
								minPingDelay: 1e4,
								maxPingDelay: config.activityTimeout
							});
							var ws_transport = defineTransportStrategy("ws", "ws", 3, ws_options, ws_manager);
							var wss_transport = defineTransportStrategy("wss", "ws", 3, wss_options, ws_manager);
							var sockjs_transport = defineTransportStrategy("sockjs", "sockjs", 1, sockjs_options);
							var xhr_streaming_transport = defineTransportStrategy("xhr_streaming", "xhr_streaming", 1, sockjs_options, streaming_manager);
							var xdr_streaming_transport = defineTransportStrategy("xdr_streaming", "xdr_streaming", 1, sockjs_options, streaming_manager);
							var xhr_polling_transport = defineTransportStrategy("xhr_polling", "xhr_polling", 1, sockjs_options);
							var xdr_polling_transport = defineTransportStrategy("xdr_polling", "xdr_polling", 1, sockjs_options);
							var ws_loop = new SequentialStrategy([ws_transport], timeouts);
							var wss_loop = new SequentialStrategy([wss_transport], timeouts);
							var sockjs_loop = new SequentialStrategy([sockjs_transport], timeouts);
							var streaming_loop = new SequentialStrategy([new IfStrategy(testSupportsStrategy(xhr_streaming_transport), xhr_streaming_transport, xdr_streaming_transport)], timeouts);
							var polling_loop = new SequentialStrategy([new IfStrategy(testSupportsStrategy(xhr_polling_transport), xhr_polling_transport, xdr_polling_transport)], timeouts);
							var http_loop = new SequentialStrategy([new IfStrategy(testSupportsStrategy(streaming_loop), new BestConnectedEverStrategy([streaming_loop, new DelayedStrategy(polling_loop, { delay: 4e3 })]), polling_loop)], timeouts);
							var http_fallback_loop = new IfStrategy(testSupportsStrategy(http_loop), http_loop, sockjs_loop);
							var wsStrategy;
							if (baseOptions.useTLS) wsStrategy = new BestConnectedEverStrategy([ws_loop, new DelayedStrategy(http_fallback_loop, { delay: 2e3 })]);
							else wsStrategy = new BestConnectedEverStrategy([
								ws_loop,
								new DelayedStrategy(wss_loop, { delay: 2e3 }),
								new DelayedStrategy(http_fallback_loop, { delay: 5e3 })
							]);
							return new WebSocketPrioritizedCachedStrategy(new FirstConnectedStrategy(new IfStrategy(testSupportsStrategy(ws_transport), wsStrategy, http_fallback_loop)), definedTransports, {
								ttl: 18e5,
								timeline: baseOptions.timeline,
								useTLS: baseOptions.useTLS
							});
						};
						const default_strategy = getDefaultStrategy;
						function transport_connection_initializer() {
							var self = this;
							self.timeline.info(self.buildTimelineMessage({ transport: self.name + (self.options.useTLS ? "s" : "") }));
							if (self.hooks.isInitialized()) self.changeState("initialized");
							else if (self.hooks.file) {
								self.changeState("initializing");
								Dependencies.load(self.hooks.file, { useTLS: self.options.useTLS }, function(error, callback) {
									if (self.hooks.isInitialized()) {
										self.changeState("initialized");
										callback(true);
									} else {
										if (error) self.onError(error);
										self.onClose();
										callback(false);
									}
								});
							} else self.onClose();
						}
						const http_xdomain_request = {
							getRequest: function(socket) {
								var xdr = new window.XDomainRequest();
								xdr.ontimeout = function() {
									socket.emit("error", new RequestTimedOut());
									socket.close();
								};
								xdr.onerror = function(e) {
									socket.emit("error", e);
									socket.close();
								};
								xdr.onprogress = function() {
									if (xdr.responseText && xdr.responseText.length > 0) socket.onChunk(200, xdr.responseText);
								};
								xdr.onload = function() {
									if (xdr.responseText && xdr.responseText.length > 0) socket.onChunk(200, xdr.responseText);
									socket.emit("finished", 200);
									socket.close();
								};
								return xdr;
							},
							abortRequest: function(xdr) {
								xdr.ontimeout = xdr.onerror = xdr.onprogress = xdr.onload = null;
								xdr.abort();
							}
						};
						const MAX_BUFFER_LENGTH = 256 * 1024;
						class HTTPRequest extends Dispatcher {
							constructor(hooks, method, url) {
								super();
								this.hooks = hooks;
								this.method = method;
								this.url = url;
							}
							start(payload) {
								this.position = 0;
								this.xhr = this.hooks.getRequest(this);
								this.unloader = () => {
									this.close();
								};
								runtime.addUnloadListener(this.unloader);
								this.xhr.open(this.method, this.url, true);
								if (this.xhr.setRequestHeader) this.xhr.setRequestHeader("Content-Type", "application/json");
								this.xhr.send(payload);
							}
							close() {
								if (this.unloader) {
									runtime.removeUnloadListener(this.unloader);
									this.unloader = null;
								}
								if (this.xhr) {
									this.hooks.abortRequest(this.xhr);
									this.xhr = null;
								}
							}
							onChunk(status, data) {
								while (true) {
									var chunk = this.advanceBuffer(data);
									if (chunk) this.emit("chunk", {
										status,
										data: chunk
									});
									else break;
								}
								if (this.isBufferTooLong(data)) this.emit("buffer_too_long");
							}
							advanceBuffer(buffer) {
								var unreadData = buffer.slice(this.position);
								var endOfLinePosition = unreadData.indexOf("\n");
								if (endOfLinePosition !== -1) {
									this.position += endOfLinePosition + 1;
									return unreadData.slice(0, endOfLinePosition);
								} else return null;
							}
							isBufferTooLong(buffer) {
								return this.position === buffer.length && buffer.length > MAX_BUFFER_LENGTH;
							}
						}
						var State;
						(function(State) {
							State[State["CONNECTING"] = 0] = "CONNECTING";
							State[State["OPEN"] = 1] = "OPEN";
							State[State["CLOSED"] = 3] = "CLOSED";
						})(State || (State = {}));
						const state = State;
						var autoIncrement = 1;
						class HTTPSocket {
							constructor(hooks, url) {
								this.hooks = hooks;
								this.session = randomNumber(1e3) + "/" + randomString(8);
								this.location = getLocation(url);
								this.readyState = state.CONNECTING;
								this.openStream();
							}
							send(payload) {
								return this.sendRaw(JSON.stringify([payload]));
							}
							ping() {
								this.hooks.sendHeartbeat(this);
							}
							close(code, reason) {
								this.onClose(code, reason, true);
							}
							sendRaw(payload) {
								if (this.readyState === state.OPEN) try {
									runtime.createSocketRequest("POST", getUniqueURL(getSendURL(this.location, this.session))).start(payload);
									return true;
								} catch (e) {
									return false;
								}
								else return false;
							}
							reconnect() {
								this.closeStream();
								this.openStream();
							}
							onClose(code, reason, wasClean) {
								this.closeStream();
								this.readyState = state.CLOSED;
								if (this.onclose) this.onclose({
									code,
									reason,
									wasClean
								});
							}
							onChunk(chunk) {
								if (chunk.status !== 200) return;
								if (this.readyState === state.OPEN) this.onActivity();
								var payload;
								switch (chunk.data.slice(0, 1)) {
									case "o":
										payload = JSON.parse(chunk.data.slice(1) || "{}");
										this.onOpen(payload);
										break;
									case "a":
										payload = JSON.parse(chunk.data.slice(1) || "[]");
										for (var i = 0; i < payload.length; i++) this.onEvent(payload[i]);
										break;
									case "m":
										payload = JSON.parse(chunk.data.slice(1) || "null");
										this.onEvent(payload);
										break;
									case "h":
										this.hooks.onHeartbeat(this);
										break;
									case "c":
										payload = JSON.parse(chunk.data.slice(1) || "[]");
										this.onClose(payload[0], payload[1], true);
										break;
								}
							}
							onOpen(options) {
								if (this.readyState === state.CONNECTING) {
									if (options && options.hostname) this.location.base = replaceHost(this.location.base, options.hostname);
									this.readyState = state.OPEN;
									if (this.onopen) this.onopen();
								} else this.onClose(1006, "Server lost session", true);
							}
							onEvent(event) {
								if (this.readyState === state.OPEN && this.onmessage) this.onmessage({ data: event });
							}
							onActivity() {
								if (this.onactivity) this.onactivity();
							}
							onError(error) {
								if (this.onerror) this.onerror(error);
							}
							openStream() {
								this.stream = runtime.createSocketRequest("POST", getUniqueURL(this.hooks.getReceiveURL(this.location, this.session)));
								this.stream.bind("chunk", (chunk) => {
									this.onChunk(chunk);
								});
								this.stream.bind("finished", (status) => {
									this.hooks.onFinished(this, status);
								});
								this.stream.bind("buffer_too_long", () => {
									this.reconnect();
								});
								try {
									this.stream.start();
								} catch (error) {
									util.defer(() => {
										this.onError(error);
										this.onClose(1006, "Could not start streaming", false);
									});
								}
							}
							closeStream() {
								if (this.stream) {
									this.stream.unbind_all();
									this.stream.close();
									this.stream = null;
								}
							}
						}
						function getLocation(url) {
							var parts = /([^\?]*)\/*(\??.*)/.exec(url);
							return {
								base: parts[1],
								queryString: parts[2]
							};
						}
						function getSendURL(url, session) {
							return url.base + "/" + session + "/xhr_send";
						}
						function getUniqueURL(url) {
							return url + (url.indexOf("?") === -1 ? "?" : "&") + "t=" + +/* @__PURE__ */ new Date() + "&n=" + autoIncrement++;
						}
						function replaceHost(url, hostname) {
							var urlParts = /(https?:\/\/)([^\/:]+)((\/|:)?.*)/.exec(url);
							return urlParts[1] + hostname + urlParts[3];
						}
						function randomNumber(max) {
							return runtime.randomInt(max);
						}
						function randomString(length) {
							var result = [];
							for (var i = 0; i < length; i++) result.push(randomNumber(32).toString(32));
							return result.join("");
						}
						const http_socket = HTTPSocket;
						const http_streaming_socket = {
							getReceiveURL: function(url, session) {
								return url.base + "/" + session + "/xhr_streaming" + url.queryString;
							},
							onHeartbeat: function(socket) {
								socket.sendRaw("[]");
							},
							sendHeartbeat: function(socket) {
								socket.sendRaw("[]");
							},
							onFinished: function(socket, status) {
								socket.onClose(1006, "Connection interrupted (" + status + ")", false);
							}
						};
						const http_polling_socket = {
							getReceiveURL: function(url, session) {
								return url.base + "/" + session + "/xhr" + url.queryString;
							},
							onHeartbeat: function() {},
							sendHeartbeat: function(socket) {
								socket.sendRaw("[]");
							},
							onFinished: function(socket, status) {
								if (status === 200) socket.reconnect();
								else socket.onClose(1006, "Connection interrupted (" + status + ")", false);
							}
						};
						const http_xhr_request = {
							getRequest: function(socket) {
								var xhr = new (runtime.getXHRAPI())();
								xhr.onreadystatechange = xhr.onprogress = function() {
									switch (xhr.readyState) {
										case 3:
											if (xhr.responseText && xhr.responseText.length > 0) socket.onChunk(xhr.status, xhr.responseText);
											break;
										case 4:
											if (xhr.responseText && xhr.responseText.length > 0) socket.onChunk(xhr.status, xhr.responseText);
											socket.emit("finished", xhr.status);
											socket.close();
											break;
									}
								};
								return xhr;
							},
							abortRequest: function(xhr) {
								xhr.onreadystatechange = null;
								xhr.abort();
							}
						};
						const http_http = {
							createStreamingSocket(url) {
								return this.createSocket(http_streaming_socket, url);
							},
							createPollingSocket(url) {
								return this.createSocket(http_polling_socket, url);
							},
							createSocket(hooks, url) {
								return new http_socket(hooks, url);
							},
							createXHR(method, url) {
								return this.createRequest(http_xhr_request, method, url);
							},
							createRequest(hooks, method, url) {
								return new HTTPRequest(hooks, method, url);
							}
						};
						http_http.createXDR = function(method, url) {
							return this.createRequest(http_xdomain_request, method, url);
						};
						const runtime = {
							nextAuthCallbackID: 1,
							auth_callbacks: {},
							ScriptReceivers,
							DependenciesReceivers,
							getDefaultStrategy: default_strategy,
							Transports: transports_transports,
							transportConnectionInitializer: transport_connection_initializer,
							HTTPFactory: http_http,
							TimelineTransport: jsonp_timeline,
							getXHRAPI() {
								return window.XMLHttpRequest;
							},
							getWebSocketAPI() {
								return window.WebSocket || window.MozWebSocket;
							},
							setup(PusherClass) {
								window.Pusher = PusherClass;
								var initializeOnDocumentBody = () => {
									this.onDocumentBody(PusherClass.ready);
								};
								if (!window.JSON) Dependencies.load("json2", {}, initializeOnDocumentBody);
								else initializeOnDocumentBody();
							},
							getDocument() {
								return document;
							},
							getProtocol() {
								return this.getDocument().location.protocol;
							},
							getAuthorizers() {
								return {
									ajax: xhr_auth,
									jsonp: jsonp_auth
								};
							},
							onDocumentBody(callback) {
								if (document.body) callback();
								else setTimeout(() => {
									this.onDocumentBody(callback);
								}, 0);
							},
							createJSONPRequest(url, data) {
								return new JSONPRequest(url, data);
							},
							createScriptRequest(src) {
								return new ScriptRequest(src);
							},
							getLocalStorage() {
								try {
									return window.localStorage;
								} catch (e) {
									return;
								}
							},
							createXHR() {
								if (this.getXHRAPI()) return this.createXMLHttpRequest();
								else return this.createMicrosoftXHR();
							},
							createXMLHttpRequest() {
								return new (this.getXHRAPI())();
							},
							createMicrosoftXHR() {
								return new ActiveXObject("Microsoft.XMLHTTP");
							},
							getNetwork() {
								return Network;
							},
							createWebSocket(url) {
								return new (this.getWebSocketAPI())(url);
							},
							createSocketRequest(method, url) {
								if (this.isXHRSupported()) return this.HTTPFactory.createXHR(method, url);
								else if (this.isXDRSupported(url.indexOf("https:") === 0)) return this.HTTPFactory.createXDR(method, url);
								else throw "Cross-origin HTTP requests are not supported";
							},
							isXHRSupported() {
								var Constructor = this.getXHRAPI();
								return Boolean(Constructor) && new Constructor().withCredentials !== void 0;
							},
							isXDRSupported(useTLS) {
								var protocol = useTLS ? "https:" : "http:";
								var documentProtocol = this.getProtocol();
								return Boolean(window["XDomainRequest"]) && documentProtocol === protocol;
							},
							addUnloadListener(listener) {
								if (window.addEventListener !== void 0) window.addEventListener("unload", listener, false);
								else if (window.attachEvent !== void 0) window.attachEvent("onunload", listener);
							},
							removeUnloadListener(listener) {
								if (window.addEventListener !== void 0) window.removeEventListener("unload", listener, false);
								else if (window.detachEvent !== void 0) window.detachEvent("onunload", listener);
							},
							randomInt(max) {
								const random = function() {
									return (window.crypto || window["msCrypto"]).getRandomValues(new Uint32Array(1))[0] / Math.pow(2, 32);
								};
								return Math.floor(random() * max);
							}
						};
						var TimelineLevel;
						(function(TimelineLevel) {
							TimelineLevel[TimelineLevel["ERROR"] = 3] = "ERROR";
							TimelineLevel[TimelineLevel["INFO"] = 6] = "INFO";
							TimelineLevel[TimelineLevel["DEBUG"] = 7] = "DEBUG";
						})(TimelineLevel || (TimelineLevel = {}));
						const level = TimelineLevel;
						class Timeline {
							constructor(key, session, options) {
								this.key = key;
								this.session = session;
								this.events = [];
								this.options = options || {};
								this.sent = 0;
								this.uniqueID = 0;
							}
							log(level, event) {
								if (level <= this.options.level) {
									this.events.push(extend({}, event, { timestamp: util.now() }));
									if (this.options.limit && this.events.length > this.options.limit) this.events.shift();
								}
							}
							error(event) {
								this.log(level.ERROR, event);
							}
							info(event) {
								this.log(level.INFO, event);
							}
							debug(event) {
								this.log(level.DEBUG, event);
							}
							isEmpty() {
								return this.events.length === 0;
							}
							send(sendfn, callback) {
								var data = extend({
									session: this.session,
									bundle: this.sent + 1,
									key: this.key,
									lib: "js",
									version: this.options.version,
									cluster: this.options.cluster,
									features: this.options.features,
									timeline: this.events
								}, this.options.params);
								this.events = [];
								sendfn(data, (error, result) => {
									if (!error) this.sent++;
									if (callback) callback(error, result);
								});
								return true;
							}
							generateUniqueID() {
								this.uniqueID++;
								return this.uniqueID;
							}
						}
						class TransportStrategy {
							constructor(name, priority, transport, options) {
								this.name = name;
								this.priority = priority;
								this.transport = transport;
								this.options = options || {};
							}
							isSupported() {
								return this.transport.isSupported({ useTLS: this.options.useTLS });
							}
							connect(minPriority, callback) {
								if (!this.isSupported()) return failAttempt(new UnsupportedStrategy(), callback);
								else if (this.priority < minPriority) return failAttempt(new TransportPriorityTooLow(), callback);
								var connected = false;
								var transport = this.transport.createConnection(this.name, this.priority, this.options.key, this.options);
								var handshake = null;
								var onInitialized = function() {
									transport.unbind("initialized", onInitialized);
									transport.connect();
								};
								var onOpen = function() {
									handshake = factory.createHandshake(transport, function(result) {
										connected = true;
										unbindListeners();
										callback(null, result);
									});
								};
								var onError = function(error) {
									unbindListeners();
									callback(error);
								};
								var onClosed = function() {
									unbindListeners();
									callback(new TransportClosed(safeJSONStringify(transport)));
								};
								var unbindListeners = function() {
									transport.unbind("initialized", onInitialized);
									transport.unbind("open", onOpen);
									transport.unbind("error", onError);
									transport.unbind("closed", onClosed);
								};
								transport.bind("initialized", onInitialized);
								transport.bind("open", onOpen);
								transport.bind("error", onError);
								transport.bind("closed", onClosed);
								transport.initialize();
								return {
									abort: () => {
										if (connected) return;
										unbindListeners();
										if (handshake) handshake.close();
										else transport.close();
									},
									forceMinPriority: (p) => {
										if (connected) return;
										if (this.priority < p) if (handshake) handshake.close();
										else transport.close();
									}
								};
							}
						}
						function failAttempt(error, callback) {
							util.defer(function() {
								callback(error);
							});
							return {
								abort: function() {},
								forceMinPriority: function() {}
							};
						}
						const { Transports: strategy_builder_Transports } = runtime;
						var defineTransport = function(config, name, type, priority, options, manager) {
							var transportClass = strategy_builder_Transports[type];
							if (!transportClass) throw new UnsupportedTransport(type);
							var enabled = (!config.enabledTransports || arrayIndexOf(config.enabledTransports, name) !== -1) && (!config.disabledTransports || arrayIndexOf(config.disabledTransports, name) === -1);
							var transport;
							if (enabled) {
								options = Object.assign({ ignoreNullOrigin: config.ignoreNullOrigin }, options);
								transport = new TransportStrategy(name, priority, manager ? manager.getAssistant(transportClass) : transportClass, options);
							} else transport = strategy_builder_UnsupportedStrategy;
							return transport;
						};
						var strategy_builder_UnsupportedStrategy = {
							isSupported: function() {
								return false;
							},
							connect: function(_, callback) {
								var deferred = util.defer(function() {
									callback(new UnsupportedStrategy());
								});
								return {
									abort: function() {
										deferred.ensureAborted();
									},
									forceMinPriority: function() {}
								};
							}
						};
						function validateOptions(options) {
							if (options == null) throw "You must pass an options object";
							if (options.cluster == null) throw "Options object must provide a cluster";
							if ("disableStats" in options) logger.warn("The disableStats option is deprecated in favor of enableStats");
						}
						const composeChannelQuery = (params, authOptions) => {
							var query = "socket_id=" + encodeURIComponent(params.socketId);
							for (var key in authOptions.params) query += "&" + encodeURIComponent(key) + "=" + encodeURIComponent(authOptions.params[key]);
							if (authOptions.paramsProvider != null) {
								let dynamicParams = authOptions.paramsProvider();
								for (var key in dynamicParams) query += "&" + encodeURIComponent(key) + "=" + encodeURIComponent(dynamicParams[key]);
							}
							return query;
						};
						const UserAuthenticator = (authOptions) => {
							if (typeof runtime.getAuthorizers()[authOptions.transport] === "undefined") throw `'${authOptions.transport}' is not a recognized auth transport`;
							return (params, callback) => {
								const query = composeChannelQuery(params, authOptions);
								runtime.getAuthorizers()[authOptions.transport](runtime, query, authOptions, AuthRequestType.UserAuthentication, callback);
							};
						};
						const user_authenticator = UserAuthenticator;
						const channel_authorizer_composeChannelQuery = (params, authOptions) => {
							var query = "socket_id=" + encodeURIComponent(params.socketId);
							query += "&channel_name=" + encodeURIComponent(params.channelName);
							for (var key in authOptions.params) query += "&" + encodeURIComponent(key) + "=" + encodeURIComponent(authOptions.params[key]);
							if (authOptions.paramsProvider != null) {
								let dynamicParams = authOptions.paramsProvider();
								for (var key in dynamicParams) query += "&" + encodeURIComponent(key) + "=" + encodeURIComponent(dynamicParams[key]);
							}
							return query;
						};
						const ChannelAuthorizer = (authOptions) => {
							if (typeof runtime.getAuthorizers()[authOptions.transport] === "undefined") throw `'${authOptions.transport}' is not a recognized auth transport`;
							return (params, callback) => {
								const query = channel_authorizer_composeChannelQuery(params, authOptions);
								runtime.getAuthorizers()[authOptions.transport](runtime, query, authOptions, AuthRequestType.ChannelAuthorization, callback);
							};
						};
						const channel_authorizer = ChannelAuthorizer;
						const ChannelAuthorizerProxy = (pusher, authOptions, channelAuthorizerGenerator) => {
							const deprecatedAuthorizerOptions = {
								authTransport: authOptions.transport,
								authEndpoint: authOptions.endpoint,
								auth: {
									params: authOptions.params,
									headers: authOptions.headers
								}
							};
							return (params, callback) => {
								channelAuthorizerGenerator(pusher.channel(params.channelName), deprecatedAuthorizerOptions).authorize(params.socketId, callback);
							};
						};
						function getConfig(opts, pusher) {
							let config = {
								activityTimeout: opts.activityTimeout || defaults.activityTimeout,
								cluster: opts.cluster,
								httpPath: opts.httpPath || defaults.httpPath,
								httpPort: opts.httpPort || defaults.httpPort,
								httpsPort: opts.httpsPort || defaults.httpsPort,
								pongTimeout: opts.pongTimeout || defaults.pongTimeout,
								statsHost: opts.statsHost || defaults.stats_host,
								unavailableTimeout: opts.unavailableTimeout || defaults.unavailableTimeout,
								wsPath: opts.wsPath || defaults.wsPath,
								wsPort: opts.wsPort || defaults.wsPort,
								wssPort: opts.wssPort || defaults.wssPort,
								enableStats: getEnableStatsConfig(opts),
								httpHost: getHttpHost(opts),
								useTLS: shouldUseTLS(opts),
								wsHost: getWebsocketHost(opts),
								userAuthenticator: buildUserAuthenticator(opts),
								channelAuthorizer: buildChannelAuthorizer(opts, pusher)
							};
							if ("disabledTransports" in opts) config.disabledTransports = opts.disabledTransports;
							if ("enabledTransports" in opts) config.enabledTransports = opts.enabledTransports;
							if ("ignoreNullOrigin" in opts) config.ignoreNullOrigin = opts.ignoreNullOrigin;
							if ("timelineParams" in opts) config.timelineParams = opts.timelineParams;
							if ("nacl" in opts) config.nacl = opts.nacl;
							return config;
						}
						function getHttpHost(opts) {
							if (opts.httpHost) return opts.httpHost;
							if (opts.cluster) return `sockjs-${opts.cluster}.pusher.com`;
							return defaults.httpHost;
						}
						function getWebsocketHost(opts) {
							if (opts.wsHost) return opts.wsHost;
							return getWebsocketHostFromCluster(opts.cluster);
						}
						function getWebsocketHostFromCluster(cluster) {
							return `ws-${cluster}.pusher.com`;
						}
						function shouldUseTLS(opts) {
							if (runtime.getProtocol() === "https:") return true;
							else if (opts.forceTLS === false) return false;
							return true;
						}
						function getEnableStatsConfig(opts) {
							if ("enableStats" in opts) return opts.enableStats;
							if ("disableStats" in opts) return !opts.disableStats;
							return false;
						}
						const hasCustomHandler = (auth) => {
							return "customHandler" in auth && auth["customHandler"] != null;
						};
						function buildUserAuthenticator(opts) {
							const userAuthentication = Object.assign(Object.assign({}, defaults.userAuthentication), opts.userAuthentication);
							if (hasCustomHandler(userAuthentication)) return userAuthentication["customHandler"];
							return user_authenticator(userAuthentication);
						}
						function buildChannelAuth(opts, pusher) {
							let channelAuthorization;
							if ("channelAuthorization" in opts) channelAuthorization = Object.assign(Object.assign({}, defaults.channelAuthorization), opts.channelAuthorization);
							else {
								channelAuthorization = {
									transport: opts.authTransport || defaults.authTransport,
									endpoint: opts.authEndpoint || defaults.authEndpoint
								};
								if ("auth" in opts) {
									if ("params" in opts.auth) channelAuthorization.params = opts.auth.params;
									if ("headers" in opts.auth) channelAuthorization.headers = opts.auth.headers;
								}
								if ("authorizer" in opts) return { customHandler: ChannelAuthorizerProxy(pusher, channelAuthorization, opts.authorizer) };
							}
							return channelAuthorization;
						}
						function buildChannelAuthorizer(opts, pusher) {
							const channelAuthorization = buildChannelAuth(opts, pusher);
							if (hasCustomHandler(channelAuthorization)) return channelAuthorization["customHandler"];
							return channel_authorizer(channelAuthorization);
						}
						class WatchlistFacade extends Dispatcher {
							constructor(pusher) {
								super(function(eventName, data) {
									logger.debug(`No callbacks on watchlist events for ${eventName}`);
								});
								this.pusher = pusher;
								this.bindWatchlistInternalEvent();
							}
							handleEvent(pusherEvent) {
								pusherEvent.data.events.forEach((watchlistEvent) => {
									this.emit(watchlistEvent.name, watchlistEvent);
								});
							}
							bindWatchlistInternalEvent() {
								this.pusher.connection.bind("message", (pusherEvent) => {
									if (pusherEvent.event === "pusher_internal:watchlist_events") this.handleEvent(pusherEvent);
								});
							}
						}
						function flatPromise() {
							let resolve, reject;
							return {
								promise: new Promise((res, rej) => {
									resolve = res;
									reject = rej;
								}),
								resolve,
								reject
							};
						}
						const flat_promise = flatPromise;
						class UserFacade extends Dispatcher {
							constructor(pusher) {
								super(function(eventName, data) {
									logger.debug("No callbacks on user for " + eventName);
								});
								this.signin_requested = false;
								this.user_data = null;
								this.serverToUserChannel = null;
								this.signinDonePromise = null;
								this._signinDoneResolve = null;
								this._onAuthorize = (err, authData) => {
									if (err) {
										logger.warn(`Error during signin: ${err}`);
										this._cleanup();
										return;
									}
									this.pusher.send_event("pusher:signin", {
										auth: authData.auth,
										user_data: authData.user_data
									});
								};
								this.pusher = pusher;
								this.pusher.connection.bind("state_change", ({ previous, current }) => {
									if (previous !== "connected" && current === "connected") this._signin();
									if (previous === "connected" && current !== "connected") {
										this._cleanup();
										this._newSigninPromiseIfNeeded();
									}
								});
								this.watchlist = new WatchlistFacade(pusher);
								this.pusher.connection.bind("message", (event) => {
									if (event.event === "pusher:signin_success") this._onSigninSuccess(event.data);
									if (this.serverToUserChannel && this.serverToUserChannel.name === event.channel) this.serverToUserChannel.handleEvent(event);
								});
							}
							signin() {
								if (this.signin_requested) return;
								this.signin_requested = true;
								this._signin();
							}
							_signin() {
								if (!this.signin_requested) return;
								this._newSigninPromiseIfNeeded();
								if (this.pusher.connection.state !== "connected") return;
								this.pusher.config.userAuthenticator({ socketId: this.pusher.connection.socket_id }, this._onAuthorize);
							}
							_onSigninSuccess(data) {
								try {
									this.user_data = JSON.parse(data.user_data);
								} catch (e) {
									logger.error(`Failed parsing user data after signin: ${data.user_data}`);
									this._cleanup();
									return;
								}
								if (typeof this.user_data.id !== "string" || this.user_data.id === "") {
									logger.error(`user_data doesn't contain an id. user_data: ${this.user_data}`);
									this._cleanup();
									return;
								}
								this._signinDoneResolve();
								this._subscribeChannels();
							}
							_subscribeChannels() {
								const ensure_subscribed = (channel) => {
									if (channel.subscriptionPending && channel.subscriptionCancelled) channel.reinstateSubscription();
									else if (!channel.subscriptionPending && this.pusher.connection.state === "connected") channel.subscribe();
								};
								this.serverToUserChannel = new Channel(`#server-to-user-${this.user_data.id}`, this.pusher);
								this.serverToUserChannel.bind_global((eventName, data) => {
									if (eventName.indexOf("pusher_internal:") === 0 || eventName.indexOf("pusher:") === 0) return;
									this.emit(eventName, data);
								});
								ensure_subscribed(this.serverToUserChannel);
							}
							_cleanup() {
								this.user_data = null;
								if (this.serverToUserChannel) {
									this.serverToUserChannel.unbind_all();
									this.serverToUserChannel.disconnect();
									this.serverToUserChannel = null;
								}
								if (this.signin_requested) this._signinDoneResolve();
							}
							_newSigninPromiseIfNeeded() {
								if (!this.signin_requested) return;
								if (this.signinDonePromise && !this.signinDonePromise.done) return;
								const { promise, resolve, reject: _ } = flat_promise();
								promise.done = false;
								const setDone = () => {
									promise.done = true;
								};
								promise.then(setDone).catch(setDone);
								this.signinDonePromise = promise;
								this._signinDoneResolve = resolve;
							}
						}
						class Pusher {
							static ready() {
								Pusher.isReady = true;
								for (var i = 0, l = Pusher.instances.length; i < l; i++) Pusher.instances[i].connect();
							}
							static getClientFeatures() {
								return keys(filterObject({ ws: runtime.Transports.ws }, function(t) {
									return t.isSupported({});
								}));
							}
							constructor(app_key, options) {
								checkAppKey(app_key);
								validateOptions(options);
								this.key = app_key;
								this.options = options;
								this.config = getConfig(this.options, this);
								this.channels = factory.createChannels();
								this.global_emitter = new Dispatcher();
								this.sessionID = runtime.randomInt(1e9);
								this.timeline = new Timeline(this.key, this.sessionID, {
									cluster: this.config.cluster,
									features: Pusher.getClientFeatures(),
									params: this.config.timelineParams || {},
									limit: 50,
									level: level.INFO,
									version: defaults.VERSION
								});
								if (this.config.enableStats) this.timelineSender = factory.createTimelineSender(this.timeline, {
									host: this.config.statsHost,
									path: "/timeline/v2/" + runtime.TimelineTransport.name
								});
								var getStrategy = (options) => {
									return runtime.getDefaultStrategy(this.config, options, defineTransport);
								};
								this.connection = factory.createConnectionManager(this.key, {
									getStrategy,
									timeline: this.timeline,
									activityTimeout: this.config.activityTimeout,
									pongTimeout: this.config.pongTimeout,
									unavailableTimeout: this.config.unavailableTimeout,
									useTLS: Boolean(this.config.useTLS)
								});
								this.connection.bind("connected", () => {
									this.subscribeAll();
									if (this.timelineSender) this.timelineSender.send(this.connection.isUsingTLS());
								});
								this.connection.bind("message", (event) => {
									var internal = event.event.indexOf("pusher_internal:") === 0;
									if (event.channel) {
										var channel = this.channel(event.channel);
										if (channel) channel.handleEvent(event);
									}
									if (!internal) this.global_emitter.emit(event.event, event.data);
								});
								this.connection.bind("connecting", () => {
									this.channels.disconnect();
								});
								this.connection.bind("disconnected", () => {
									this.channels.disconnect();
								});
								this.connection.bind("error", (err) => {
									logger.warn(err);
								});
								Pusher.instances.push(this);
								this.timeline.info({ instances: Pusher.instances.length });
								this.user = new UserFacade(this);
								if (Pusher.isReady) this.connect();
							}
							switchCluster(options) {
								const { appKey, cluster } = options;
								this.key = appKey;
								this.options = Object.assign(Object.assign({}, this.options), { cluster });
								this.config = getConfig(this.options, this);
								this.connection.switchCluster(this.key);
							}
							channel(name) {
								return this.channels.find(name);
							}
							allChannels() {
								return this.channels.all();
							}
							connect() {
								this.connection.connect();
								if (this.timelineSender) {
									if (!this.timelineSenderTimer) {
										var usingTLS = this.connection.isUsingTLS();
										var timelineSender = this.timelineSender;
										this.timelineSenderTimer = new PeriodicTimer(6e4, function() {
											timelineSender.send(usingTLS);
										});
									}
								}
							}
							disconnect() {
								this.connection.disconnect();
								if (this.timelineSenderTimer) {
									this.timelineSenderTimer.ensureAborted();
									this.timelineSenderTimer = null;
								}
							}
							bind(event_name, callback, context) {
								this.global_emitter.bind(event_name, callback, context);
								return this;
							}
							unbind(event_name, callback, context) {
								this.global_emitter.unbind(event_name, callback, context);
								return this;
							}
							bind_global(callback) {
								this.global_emitter.bind_global(callback);
								return this;
							}
							unbind_global(callback) {
								this.global_emitter.unbind_global(callback);
								return this;
							}
							unbind_all(callback) {
								this.global_emitter.unbind_all();
								return this;
							}
							subscribeAll() {
								var channelName;
								for (channelName in this.channels.channels) if (this.channels.channels.hasOwnProperty(channelName)) this.subscribe(channelName);
							}
							subscribe(channel_name) {
								var channel = this.channels.add(channel_name, this);
								if (channel.subscriptionPending && channel.subscriptionCancelled) channel.reinstateSubscription();
								else if (!channel.subscriptionPending && this.connection.state === "connected") channel.subscribe();
								return channel;
							}
							unsubscribe(channel_name) {
								var channel = this.channels.find(channel_name);
								if (channel && channel.subscriptionPending) channel.cancelSubscription();
								else {
									channel = this.channels.remove(channel_name);
									if (channel && channel.subscribed) channel.unsubscribe();
								}
							}
							send_event(event_name, data, channel) {
								return this.connection.send_event(event_name, data, channel);
							}
							shouldUseTLS() {
								return this.config.useTLS;
							}
							signin() {
								this.user.signin();
							}
						}
						Pusher.instances = [];
						Pusher.isReady = false;
						Pusher.logToConsole = false;
						Pusher.Runtime = runtime;
						Pusher.ScriptReceivers = runtime.ScriptReceivers;
						Pusher.DependenciesReceivers = runtime.DependenciesReceivers;
						Pusher.auth_callbacks = runtime.auth_callbacks;
						const pusher = Pusher;
						function checkAppKey(key) {
							if (key === null || key === void 0) throw "You must pass your app key when you instantiate Pusher.";
						}
						runtime.setup(Pusher);
					}
				};
				var __webpack_module_cache__ = {};
				function __webpack_require__(moduleId) {
					var cachedModule = __webpack_module_cache__[moduleId];
					if (cachedModule !== void 0) return cachedModule.exports;
					var module$2 = __webpack_module_cache__[moduleId] = { exports: {} };
					__webpack_modules__[moduleId].call(module$2.exports, module$2, module$2.exports, __webpack_require__);
					return module$2.exports;
				}
				__webpack_require__.d = (exports$3, definition) => {
					for (var key in definition) if (__webpack_require__.o(definition, key) && !__webpack_require__.o(exports$3, key)) Object.defineProperty(exports$3, key, {
						enumerable: true,
						get: definition[key]
					});
				};
				__webpack_require__.o = (obj, prop) => Object.prototype.hasOwnProperty.call(obj, prop);
				return __webpack_require__(721);
			})();
		});
	}));
	//#endregion
	return (/* @__PURE__ */ __commonJSMin(((exports, module) => {
		init_echo();
		var import_pusher = /* @__PURE__ */ __toESM(require_pusher(), 1);
		if (typeof window !== "undefined") {
			window.Pusher = import_pusher.default;
			window.Echo = E;
		}
		(function(global) {
			"use strict";
			const WIDGET_VERSION = "1.1.0";
			const MAX_ATTACHMENTS = 3;
			const WIDGET_SCRIPT = document.currentScript || document.querySelector("script[data-widget-key]") || document.querySelector("script[src*=\"widget.js\"]");
			const WIDGET_HOST = new URL(WIDGET_SCRIPT?.src || window.location.href, window.location.href).origin;
			let state = {
				isOpen: false,
				isInitialized: false,
				config: null,
				visitorName: null,
				visitorEmail: null,
				conversationId: null,
				lastCursor: null,
				pollingInterval: null,
				projectId: null,
				selectedAttachments: [],
				isOnline: navigator.onLine ?? true,
				pollingPaused: false,
				wsReconnectAttempts: 0,
				maxWsReconnectAttempts: 3,
				useWebSocket: false,
				wsEcho: null,
				typingTimeout: null,
				messageIds: /* @__PURE__ */ new Set()
			};
			let elements = {};
			const utils = {
				generateId() {
					return `widget_${Date.now()}_${Math.random().toString(36).slice(2, 11)}`;
				},
				formatTime(date) {
					return new Date(date).toLocaleTimeString("en-US", {
						hour: "2-digit",
						minute: "2-digit",
						hour12: true
					});
				},
				formatFileSize(size) {
					if (!Number.isFinite(size) || size <= 0) return "";
					if (size < 1024) return `${size} B`;
					if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`;
					return `${(size / (1024 * 1024)).toFixed(1)} MB`;
				},
				escapeHtml(text) {
					const div = document.createElement("div");
					div.textContent = text ?? "";
					return div.innerHTML;
				},
				sanitizeUrl(url) {
					if (typeof url !== "string" || !url) return "";
					const trimmed = url.trim();
					for (const prefix of [
						"javascript:",
						"vbscript:",
						"data:text/html",
						"data:image/svg+xml",
						"data:application/xml",
						"data:text/xml"
					]) if (trimmed.toLowerCase().startsWith(prefix)) return "";
					if (/^https?:\/\//i.test(trimmed) || /^\/[^/]/.test(trimmed) || /^[^/]/.test(trimmed)) return trimmed;
					return "";
				},
				getWidgetKey() {
					const runtimeConfig = this.getBootstrapConfig();
					return WIDGET_SCRIPT?.dataset.widgetKey || runtimeConfig?.widget_key || runtimeConfig?.widgetKey || null;
				},
				getBootstrapToken() {
					const runtimeConfig = this.getBootstrapConfig();
					return runtimeConfig?.bootstrap_token || runtimeConfig?.bootstrapToken || null;
				},
				getBootstrapConfig() {
					return this.normalizeConfig(global.WIDGET_CONFIG);
				},
				normalizeConfig(config) {
					if (!config || typeof config !== "object") return null;
					const settings = config.settings ?? {};
					return {
						project_id: config.projectId ?? config.project_id ?? null,
						project_name: config.projectName ?? config.project_name ?? null,
						widget_key: config.widgetKey ?? config.widget_key ?? null,
						bootstrap_token: config.bootstrapToken ?? config.bootstrap_token ?? null,
						trusted_origin: config.trustedOrigin ?? config.trusted_origin ?? null,
						settings,
						theme: settings.theme ?? config.theme ?? "light",
						position: settings.position ?? config.position ?? "bottom-right",
						width: settings.width ?? config.width ?? 350,
						height: settings.height ?? config.height ?? 500,
						primary_color: settings.primary_color ?? config.primary_color ?? "#3B82F6",
						custom_css: settings.custom_css ?? config.custom_css ?? null,
						verified_domains: config.verifiedDomains ?? config.verified_domains ?? [],
						api_base_url: config.apiBaseUrl ?? config.api_base_url ?? WIDGET_HOST,
						app_origin: config.appOrigin ?? config.app_origin ?? WIDGET_HOST
					};
				},
				clearLegacyVisitorToken() {
					try {
						sessionStorage.removeItem("widget_visitor_token");
					} catch (error) {
						console.warn("[Widget] Failed to clear legacy visitor token cache.", error);
					}
				},
				attachmentName(attachment) {
					return attachment?.original_name || attachment?.name || "attachment";
				},
				isImageAttachment(attachment) {
					return typeof attachment?.mime_type === "string" && attachment.mime_type.startsWith("image/");
				}
			};
			const api = {
				async request(endpoint, options = {}) {
					return this.requestWithRetry(endpoint, options, 3);
				},
				async requestWithRetry(endpoint, options, maxRetries) {
					let lastError = null;
					for (let attempt = 1; attempt <= maxRetries; attempt++) try {
						return await this.requestOnce(endpoint, options);
					} catch (error) {
						lastError = error;
						if (error.status === 401 || error.status === 403) throw error;
						if (attempt === maxRetries) break;
						const delay = Math.pow(2, attempt - 1) * 1e3;
						console.warn(`[Widget] Request failed (attempt ${attempt}/${maxRetries}), retrying in ${delay}ms:`, error.message);
						await new Promise((resolve) => setTimeout(resolve, delay));
					}
					throw lastError;
				},
				async requestOnce(endpoint, options = {}) {
					const bootstrapToken = utils.getBootstrapToken();
					const widgetKey = utils.getWidgetKey();
					if (!bootstrapToken && !widgetKey) throw Object.assign(/* @__PURE__ */ new Error("Widget authentication not found"), { status: 401 });
					const baseUrl = utils.getBootstrapConfig()?.api_base_url || state.config?.api_base_url || WIDGET_HOST;
					const url = new URL(endpoint, baseUrl);
					const isFormData = options.body instanceof FormData;
					const defaultHeaders = { Accept: "application/json" };
					if (!isFormData) defaultHeaders["Content-Type"] = "application/json";
					if (bootstrapToken) defaultHeaders["X-Widget-Bootstrap"] = bootstrapToken;
					else if (widgetKey) defaultHeaders["X-Widget-Key"] = widgetKey;
					const controller = new AbortController();
					const timeoutId = setTimeout(() => controller.abort(), 1e4);
					try {
						const response = await fetch(url.toString(), {
							credentials: "include",
							...options,
							headers: {
								...defaultHeaders,
								...options.headers || {}
							},
							signal: controller.signal
						});
						if (!response.ok) {
							const error = await response.json().catch(() => ({}));
							throw Object.assign(new Error(error.error || `HTTP ${response.status}`), { status: response.status });
						}
						const payload = await response.json();
						this.applyAuthPayload(payload);
						return payload;
					} catch (error) {
						if (error.name === "AbortError") throw Object.assign(/* @__PURE__ */ new Error("Request timed out after 10 seconds"), { status: 408 });
						throw error;
					} finally {
						clearTimeout(timeoutId);
					}
				},
				applyAuthPayload(payload) {
					if (!payload || typeof payload !== "object" || !payload.bootstrap_token) return;
					if (!global.WIDGET_CONFIG || typeof global.WIDGET_CONFIG !== "object") global.WIDGET_CONFIG = {};
					global.WIDGET_CONFIG.bootstrapToken = payload.bootstrap_token;
					global.WIDGET_CONFIG.trustedOrigin = payload.trusted_origin || global.WIDGET_CONFIG.trustedOrigin || null;
				},
				async fetchConfig() {
					return utils.normalizeConfig(await this.request("/api/widget/config"));
				},
				async fetchMessages(cursor = null) {
					const query = cursor ? `?cursor=${encodeURIComponent(cursor)}` : "";
					return this.request(`/api/widget/messages${query}`);
				},
				async sendMessage(message, visitorName, visitorEmail, attachments = []) {
					if (attachments.length > 0) {
						const body = new FormData();
						if (message) body.append("message", message);
						if (visitorName) body.append("visitor_name", visitorName);
						if (visitorEmail) body.append("visitor_email", visitorEmail);
						attachments.forEach((file) => body.append("attachments[]", file));
						return this.request("/api/widget/messages", {
							method: "POST",
							body
						});
					}
					return this.request("/api/widget/messages", {
						method: "POST",
						body: JSON.stringify({
							message,
							visitor_name: visitorName,
							visitor_email: visitorEmail
						})
					});
				}
			};
			const dom = {
				createToggleBtn() {
					const btn = document.createElement("button");
					btn.id = "widget-toggle-btn";
					btn.className = `position-${state.config?.position || "bottom-right"}`;
					btn.setAttribute("aria-label", "Open chat");
					btn.innerHTML = `
        <svg class="widget-icon widget-icon-chat" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
        </svg>
        <svg class="widget-icon widget-icon-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      `;
					return btn;
				},
				createChatContainer() {
					const container = document.createElement("div");
					container.id = "widget-chat-container";
					container.className = `position-${state.config?.position || "bottom-right"}`;
					container.dataset.theme = state.config?.theme || "light";
					container.innerHTML = `
        <div id="widget-header">
          <div id="widget-header-info">
            <div id="widget-avatar">💬</div>
            <div id="widget-header-text">
              <h3>${utils.escapeHtml(state.config?.project_name || "Chat Support")}</h3>
              <p>Reply from your site inbox or Telegram</p>
            </div>
          </div>
          <button id="widget-close-btn" aria-label="Close chat">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
          </button>
        </div>

        <div id="widget-pre-chat-form">
          <h4>Welcome! 👋</h4>
          <p>Please introduce yourself so we can better assist you.</p>
          <div class="widget-form-field">
            <label for="widget-visitor-name">Your Name *</label>
            <input type="text" id="widget-visitor-name" placeholder="John Doe" required>
          </div>
          <div class="widget-form-field">
            <label for="widget-visitor-email">Email Address</label>
            <input type="email" id="widget-visitor-email" placeholder="john@example.com">
          </div>
          <button id="widget-start-chat-btn">Start Chat</button>
        </div>

        <div id="widget-messages" class="widget-hidden">
          <div id="widget-welcome">
            <div id="widget-welcome-icon">👋</div>
            <h4>Welcome to ${utils.escapeHtml(state.config?.project_name || "our support")}</h4>
            <p>Send us a message or attachment and we'll get back to you shortly.</p>
          </div>
        </div>

        <div id="widget-input-area" class="widget-hidden">
          <input id="widget-attachment-input" type="file" multiple class="widget-hidden" accept="image/*,.pdf,.txt,.doc,.docx">
          <div id="widget-composer">
            <div id="widget-attachment-list" class="widget-hidden"></div>
            <div id="widget-input-row">
              <button id="widget-attachment-btn" type="button" aria-label="Attach files">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21.44 11.05l-8.49 8.49a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.82-2.83l8.49-8.48"></path>
                </svg>
              </button>
              <textarea id="widget-input" placeholder="Type your message..." rows="1" maxlength="2000"></textarea>
              <button id="widget-send-btn" aria-label="Send message">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="22" y1="2" x2="11" y2="13"></line>
                  <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                </svg>
              </button>
            </div>
          </div>
        </div>

        <div id="widget-loading">
          <div class="widget-spinner"></div>
        </div>

        <div id="widget-error">
          <span id="widget-error-message"></span>
        </div>
      `;
					return container;
				}
			};
			const ui = {
				init() {
					if (document.getElementById("widget-chat-container")) return;
					elements.toggleBtn = dom.createToggleBtn();
					document.body.appendChild(elements.toggleBtn);
					elements.container = dom.createChatContainer();
					document.body.appendChild(elements.container);
					elements.preChatForm = document.getElementById("widget-pre-chat-form");
					elements.messages = document.getElementById("widget-messages");
					elements.inputArea = document.getElementById("widget-input-area");
					elements.input = document.getElementById("widget-input");
					elements.sendBtn = document.getElementById("widget-send-btn");
					elements.closeBtn = document.getElementById("widget-close-btn");
					elements.loading = document.getElementById("widget-loading");
					elements.error = document.getElementById("widget-error");
					elements.nameInput = document.getElementById("widget-visitor-name");
					elements.emailInput = document.getElementById("widget-visitor-email");
					elements.startChatBtn = document.getElementById("widget-start-chat-btn");
					elements.attachmentBtn = document.getElementById("widget-attachment-btn");
					elements.attachmentInput = document.getElementById("widget-attachment-input");
					elements.attachmentList = document.getElementById("widget-attachment-list");
					this.bindEvents();
					this.applyDynamicColors();
					if (state.config?.custom_css) {
						const style = document.createElement("style");
						style.textContent = state.config.custom_css;
						elements.container.appendChild(style);
					}
				},
				applyDynamicColors() {
					const primaryColor = state.config?.primary_color || "#3B82F6";
					if (elements.container) {
						elements.container.style.setProperty("--w-bg-header", primaryColor);
						elements.container.style.setProperty("--w-bg-message-visitor", primaryColor);
					}
					const toggleBtn = document.getElementById("widget-toggle-btn");
					if (toggleBtn) {
						toggleBtn.style.setProperty("background", primaryColor, "important");
						toggleBtn.style.boxShadow = `0 10px 25px -5px ${primaryColor}66`;
					}
				},
				bindEvents() {
					elements.toggleBtn.addEventListener("click", () => toggle());
					elements.closeBtn.addEventListener("click", () => close());
					elements.startChatBtn.addEventListener("click", () => this.startChat());
					elements.sendBtn.addEventListener("click", () => this.sendMessage());
					elements.attachmentBtn.addEventListener("click", () => elements.attachmentInput.click());
					elements.attachmentInput.addEventListener("change", (event) => this.handleAttachmentSelection(event));
					elements.input.addEventListener("keydown", (event) => {
						if (event.key === "Enter" && !event.shiftKey) {
							event.preventDefault();
							this.sendMessage();
						}
					});
					elements.input.addEventListener("input", () => {
						elements.input.style.height = "auto";
						elements.input.style.height = `${Math.min(elements.input.scrollHeight, 120)}px`;
					});
					elements.nameInput.addEventListener("keydown", (event) => {
						if (event.key === "Enter") this.startChat();
					});
				},
				async startChat() {
					const name = elements.nameInput.value.trim();
					const email = elements.emailInput.value.trim();
					if (!name) {
						elements.nameInput.style.borderColor = "#dc2626";
						setTimeout(() => {
							elements.nameInput.style.borderColor = "";
						}, 2e3);
						return;
					}
					state.visitorName = name;
					state.visitorEmail = email || null;
					elements.preChatForm.classList.add("widget-hidden");
					elements.messages.classList.remove("widget-hidden");
					elements.inputArea.classList.remove("widget-hidden");
					elements.input.focus();
					await this.loadMessages();
					if (!this.initWebSocket(state.conversationId)) startPolling();
				},
				initWebSocket(conversationId) {
					if (!conversationId) return false;
					const reverbConfig = state.config?.reverb;
					if (!reverbConfig?.app_key) {
						console.log("[Widget] Reverb not configured, using polling fallback.");
						return false;
					}
					if (typeof import_pusher.default === "undefined") {
						console.log("[Widget] Pusher not loaded, using polling fallback.");
						return false;
					}
					try {
						const bootstrapToken = utils.getBootstrapToken();
						const widgetKey = utils.getWidgetKey();
						const echo = new E({
							broadcaster: "pusher",
							key: reverbConfig.app_key,
							wsHost: reverbConfig.host,
							wsPort: reverbConfig.port || (reverbConfig.secure ? 443 : 80),
							wssPort: reverbConfig.port || 443,
							forceTLS: reverbConfig.secure !== false,
							disableStats: true,
							enabledTransports: ["ws", "wss"],
							cluster: "mt1",
							authorizer: (channel) => {
								return { authorize: (socketId, callback) => {
									const headers = {};
									if (bootstrapToken) headers["X-Widget-Bootstrap"] = bootstrapToken;
									else if (widgetKey) headers["X-Widget-Key"] = widgetKey;
									fetch("/broadcasting/auth", {
										method: "POST",
										headers: {
											"Content-Type": "application/json",
											"Accept": "application/json",
											...headers
										},
										body: JSON.stringify({
											channel_name: channel.name,
											socket_id: socketId
										}),
										credentials: "include"
									}).then((response) => response.json()).then((data) => callback(false, data)).catch((error) => callback(true, error));
								} };
							}
						});
						state.wsEcho = echo;
						state.useWebSocket = true;
						state.wsReconnectAttempts = 0;
						echo.private(`widget.conversation.${conversationId}`).listen(".widget.message-sent", (data) => {
							console.log("[Widget] Received WebSocket message:", data);
							if (data.message) this.addMessage(data.message);
						}).listen(".widget.typing", (data) => {
							console.log("[Widget] Received typing event:", data);
							if (data.typing) this.showTypingIndicator(data.agent_name || "Agent");
							else this.hideTypingIndicator();
						});
						echo.connector.connection.addEventListener("close", () => {
							console.log("[Widget] WebSocket disconnected, attempting reconnect...");
							this.handleWsReconnect(conversationId);
						});
						console.log("[Widget] WebSocket connected for conversation", conversationId);
						return true;
					} catch (error) {
						console.error("[Widget] WebSocket initialization failed:", error);
						state.useWebSocket = false;
						return false;
					}
				},
				handleWsReconnect(conversationId) {
					if (state.wsReconnectAttempts >= state.maxWsReconnectAttempts) {
						console.log("[Widget] Max WebSocket reconnect attempts reached, falling back to polling.");
						state.useWebSocket = false;
						startPolling();
						return;
					}
					state.wsReconnectAttempts++;
					const delay = state.wsReconnectAttempts * 2e3;
					console.log(`[Widget] Reconnect attempt ${state.wsReconnectAttempts}/${state.maxWsReconnectAttempts} in ${delay}ms`);
					setTimeout(() => {
						if (state.isOpen && state.conversationId) this.initWebSocket(conversationId);
					}, delay);
				},
				showTypingIndicator(agentName) {
					this.hideTypingIndicator();
					const typingEl = document.createElement("div");
					typingEl.id = "widget-typing-indicator";
					typingEl.className = "widget-message-typing";
					typingEl.innerHTML = `
        <div class="widget-typing-dot"></div>
        <div class="widget-typing-dot"></div>
        <div class="widget-typing-dot"></div>
      `;
					elements.messages?.appendChild(typingEl);
					this.scrollToBottom();
					if (state.typingTimeout) clearTimeout(state.typingTimeout);
					state.typingTimeout = setTimeout(() => {
						this.hideTypingIndicator();
					}, 5e3);
				},
				hideTypingIndicator() {
					const typingEl = document.getElementById("widget-typing-indicator");
					if (typingEl) typingEl.remove();
					if (state.typingTimeout) {
						clearTimeout(state.typingTimeout);
						state.typingTimeout = null;
					}
				},
				handleAttachmentSelection(event) {
					const nextFiles = Array.from(event.target.files || []);
					const merged = [...state.selectedAttachments, ...nextFiles].slice(0, MAX_ATTACHMENTS);
					if (nextFiles.length + state.selectedAttachments.length > MAX_ATTACHMENTS) this.showError(`You can attach up to ${MAX_ATTACHMENTS} files.`);
					state.selectedAttachments = merged;
					this.renderAttachmentComposer();
					event.target.value = "";
				},
				renderAttachmentComposer() {
					if (!elements.attachmentList) return;
					if (state.selectedAttachments.length === 0) {
						elements.attachmentList.innerHTML = "";
						elements.attachmentList.classList.add("widget-hidden");
						return;
					}
					elements.attachmentList.classList.remove("widget-hidden");
					elements.attachmentList.innerHTML = state.selectedAttachments.map((file, index) => `
        <div class="widget-attachment-chip" data-attachment-index="${index}">
          <span class="widget-attachment-chip-name">${utils.escapeHtml(file.name)}</span>
          <span class="widget-attachment-chip-size">${utils.escapeHtml(utils.formatFileSize(file.size))}</span>
          <button type="button" class="widget-attachment-chip-remove" aria-label="Remove attachment" data-attachment-index="${index}">×</button>
        </div>
      `).join("");
					elements.attachmentList.querySelectorAll(".widget-attachment-chip-remove").forEach((button) => {
						button.addEventListener("click", () => {
							const index = Number(button.dataset.attachmentIndex);
							state.selectedAttachments = state.selectedAttachments.filter((_, itemIndex) => itemIndex !== index);
							this.renderAttachmentComposer();
						});
					});
				},
				resetAttachmentComposer() {
					state.selectedAttachments = [];
					this.renderAttachmentComposer();
				},
				async sendMessage() {
					const text = elements.input.value.trim();
					const attachments = [...state.selectedAttachments];
					if (!text && attachments.length === 0) return;
					elements.input.disabled = true;
					elements.sendBtn.disabled = true;
					elements.attachmentBtn.disabled = true;
					const tempId = utils.generateId();
					this.addMessage({
						id: tempId,
						body: text || null,
						type: "visitor",
						created_at: (/* @__PURE__ */ new Date()).toISOString(),
						attachments: attachments.map((file) => ({
							original_name: file.name,
							mime_type: file.type || "application/octet-stream",
							size: file.size
						})),
						isPending: true
					});
					elements.input.value = "";
					elements.input.style.height = "auto";
					this.resetAttachmentComposer();
					try {
						const response = await api.sendMessage(text, state.visitorName, state.visitorEmail, attachments);
						if (response.conversation_id) state.conversationId = response.conversation_id;
						if (response.message) this.replacePendingMessage(tempId, response.message);
						else {
							const messageEl = document.querySelector(`[data-message-id="${tempId}"]`);
							if (messageEl) {
								const finalId = response.message_id || response.message?.id || tempId;
								messageEl.dataset.messageId = finalId;
								messageEl.classList.remove("widget-message-pending");
							}
						}
					} catch (error) {
						console.error("Failed to send message:", error);
						const messageEl = document.querySelector(`[data-message-id="${tempId}"]`);
						if (messageEl) {
							messageEl.classList.add("widget-message-failed");
							this.addRetryButton(messageEl, text, attachments);
						}
						this.showError(error.message || "Failed to send message.");
					} finally {
						elements.input.disabled = false;
						elements.sendBtn.disabled = false;
						elements.attachmentBtn.disabled = false;
						elements.input.focus();
					}
				},
				addRetryButton(messageEl, text, attachments) {
					const retryBtn = document.createElement("button");
					retryBtn.className = "widget-message-retry-btn";
					retryBtn.textContent = "↻ Retry";
					retryBtn.setAttribute("aria-label", "Retry sending message");
					retryBtn.addEventListener("click", async () => {
						retryBtn.disabled = true;
						retryBtn.textContent = "Retrying...";
						messageEl.classList.remove("widget-message-failed");
						messageEl.classList.add("widget-message-pending");
						retryBtn.remove();
						try {
							const response = await api.sendMessage(text, state.visitorName, state.visitorEmail, attachments);
							if (response.message) this.replacePendingMessage(messageEl.dataset.messageId, response.message);
							else messageEl.classList.remove("widget-message-pending");
						} catch (retryError) {
							console.error("Retry failed:", retryError);
							messageEl.classList.add("widget-message-failed");
							this.addRetryButton(messageEl, text, attachments);
							this.showError(retryError.message || "Retry failed.");
						}
					});
					const timeEl = messageEl.querySelector(".widget-message-time");
					if (timeEl) timeEl.appendChild(retryBtn);
					else messageEl.appendChild(retryBtn);
				},
				replacePendingMessage(tempId, message) {
					const pending = document.querySelector(`[data-message-id="${tempId}"]`);
					if (!pending) {
						this.addMessage(message);
						return;
					}
					const replacement = this.buildMessageElement(message);
					pending.replaceWith(replacement);
					this.scrollToBottom();
				},
				buildMessageElement(message) {
					const isVisitor = message.type === "visitor" || message.direction === "inbound";
					const messageEl = document.createElement("div");
					messageEl.className = `widget-message widget-message-${isVisitor ? "visitor" : "agent"}`;
					messageEl.dataset.messageId = message.id;
					state.messageIds.add(String(message.id));
					if (message.isPending) messageEl.classList.add("widget-message-pending");
					if (message.isFailed) messageEl.classList.add("widget-message-failed");
					const time = utils.formatTime(message.created_at);
					const bodyMarkup = message.body ? `<div class="widget-message-content">${utils.escapeHtml(message.body)}</div>` : "";
					const agentName = message.agent_name || null;
					const agentNameMarkup = !isVisitor && agentName ? `<div class="widget-message-agent-name">${utils.escapeHtml(agentName)}</div>` : "";
					const attachments = Array.isArray(message.attachments) ? message.attachments : [];
					messageEl.innerHTML = `
        ${agentNameMarkup}
        ${bodyMarkup}
        ${attachments.length > 0 ? `<div class="widget-message-attachments">${attachments.map((attachment) => {
						const label = utils.escapeHtml(utils.attachmentName(attachment));
						const meta = utils.escapeHtml(utils.formatFileSize(attachment.size));
						const sanitizedUrl = utils.sanitizeUrl(attachment.url);
						if (sanitizedUrl) return `
                <a class="widget-attachment-link" href="${utils.escapeHtml(sanitizedUrl)}" target="_blank" rel="noopener noreferrer">
                  <span class="widget-attachment-link-name">${label}</span>
                  ${meta ? `<span class="widget-attachment-link-size">${meta}</span>` : ""}
                </a>
              `;
						return `
              <div class="widget-attachment-link widget-attachment-link-static">
                <span class="widget-attachment-link-name">${label}</span>
                ${meta ? `<span class="widget-attachment-link-size">${meta}</span>` : ""}
              </div>
            `;
					}).join("")}</div>` : ""}
        <div class="widget-message-time">${time}</div>
      `;
					return messageEl;
				},
				addMessage(message) {
					const messageEl = this.buildMessageElement(message);
					const welcome = document.getElementById("widget-welcome");
					if (welcome && welcome.parentNode === elements.messages) welcome.remove();
					elements.messages.appendChild(messageEl);
					this.scrollToBottom();
				},
				scrollToBottom() {
					elements.messages.scrollTop = elements.messages.scrollHeight;
				},
				showLoading() {
					elements.loading?.classList.add("active");
				},
				hideLoading() {
					elements.loading?.classList.remove("active");
				},
				showError(message) {
					const errorEl = document.getElementById("widget-error-message");
					if (errorEl) errorEl.textContent = message;
					elements.error?.classList.add("active");
					setTimeout(() => {
						elements.error?.classList.remove("active");
					}, 5e3);
				},
				async loadMessages() {
					try {
						const response = await api.fetchMessages();
						if (response.messages && response.messages.length > 0) {
							const incomingIds = new Set(response.messages.map((m) => String(m.id)));
							const toRemove = [];
							if (elements.messages) elements.messages.querySelectorAll("[data-message-id]").forEach((el) => {
								if (!incomingIds.has(String(el.dataset.messageId))) toRemove.push(el);
							});
							toRemove.forEach((el) => el.remove());
							response.messages.forEach((message) => {
								if (!state.messageIds.has(String(message.id))) this.addMessage(message);
							});
							state.conversationId = response.messages[response.messages.length - 1]?.conversation_id || state.conversationId;
						}
						state.lastCursor = response.next_cursor || null;
						this.cleanupMessageIds();
					} catch (error) {
						console.error("Failed to load messages:", error);
					}
				},
				cleanupMessageIds() {
					if (!elements.messages || state.messageIds.size === 0) return;
					const domIds = /* @__PURE__ */ new Set();
					elements.messages.querySelectorAll("[data-message-id]").forEach((el) => {
						domIds.add(String(el.dataset.messageId));
					});
					[...state.messageIds].filter((id) => !domIds.has(id)).forEach((id) => state.messageIds.delete(id));
				},
				async pollMessages() {
					if (!state.isOpen || !state.conversationId || !state.isOnline || state.pollingPaused) return;
					try {
						const response = await api.fetchMessages();
						if (response.messages && response.messages.length > 0) response.messages.forEach((message) => {
							if (!state.messageIds.has(String(message.id))) this.addMessage(message);
						});
						state.lastCursor = response.next_cursor || null;
					} catch (error) {
						console.error("Polling error:", error);
						if (error.status === 401) {
							stopPolling();
							this.showError("Session expired. Please refresh the page.");
						} else if (error.status === 403) {
							stopPolling();
							this.showError("This chat is currently unavailable.");
						}
					}
				}
			};
			async function init() {
				if (state.isInitialized) return;
				try {
					utils.clearLegacyVisitorToken();
					state.config = await api.fetchConfig();
					state.projectId = state.config.project_id;
					ui.init();
					if (window.location.hash === "#chat" || window.location.hash === "#support") open();
					state.isInitialized = true;
					window.addEventListener("online", () => {
						state.isOnline = true;
						console.log("[Widget] Connection restored.");
						if (state.isOpen && state.conversationId && !state.useWebSocket) startPolling();
						if (state.isOpen && state.conversationId && state.wsReconnectAttempts > 0) {
							state.wsReconnectAttempts = 0;
							ui.initWebSocket(state.conversationId);
						}
					});
					window.addEventListener("offline", () => {
						state.isOnline = false;
						console.log("[Widget] Connection lost.");
						stopPolling();
						ui.showError("No internet connection. Messages will be sent when you're back online.");
					});
					document.addEventListener("visibilitychange", () => {
						if (document.hidden) {
							state.pollingPaused = true;
							console.log("[Widget] Tab hidden, pausing polling.");
						} else {
							state.pollingPaused = false;
							console.log("[Widget] Tab visible, resuming polling.");
							if (state.isOpen && state.conversationId && state.isOnline && !state.useWebSocket) startPolling();
							if (state.isOpen && state.conversationId && state.useWebSocket && state.wsEcho) try {
								const connector = state.wsEcho.connector;
								if (connector && connector.connection && connector.connection.readyState === WebSocket.CLOSED) {
									console.log("[Widget] WebSocket closed, attempting reconnect.");
									ui.initWebSocket(state.conversationId);
								}
							} catch (e) {}
						}
					});
					window.dispatchEvent(new CustomEvent("widget:ready", { detail: {
						version: WIDGET_VERSION,
						projectId: state.config.project_id
					} }));
					console.log(`[Widget] v${WIDGET_VERSION} initialized for project ${state.config.project_name}`);
				} catch (error) {
					console.error("[Widget] Initialization failed:", error);
					if (error.status === 401) ui.showError("Widget configuration is invalid. Please check the embed code.");
					else if (error.status === 403) ui.showError("This chat is currently unavailable.");
					else if (error.status === 408) ui.showError("Connection timed out. Please try again later.");
					else if (!state.isOnline) ui.showError("No internet connection. Please check your network.");
					else ui.showError("Failed to load widget. Please refresh the page.");
				}
			}
			function open() {
				if (!state.isInitialized) {
					init().then(() => open());
					return;
				}
				state.isOpen = true;
				elements.container?.classList.add("widget-open");
				elements.toggleBtn?.classList.add("widget-open");
				elements.toggleBtn?.setAttribute("aria-label", "Close chat");
				window.dispatchEvent(new CustomEvent("widget:open"));
			}
			function close() {
				state.isOpen = false;
				stopPolling();
				elements.container?.classList.remove("widget-open");
				elements.toggleBtn?.classList.remove("widget-open");
				elements.toggleBtn?.setAttribute("aria-label", "Open chat");
				window.dispatchEvent(new CustomEvent("widget:close"));
			}
			function toggle() {
				if (state.isOpen) close();
				else open();
			}
			function setMessage(text) {
				if (elements.input) elements.input.value = text;
			}
			function startPolling() {
				stopPolling();
				state.pollingInterval = setInterval(() => {
					ui.pollMessages();
				}, 5e3);
			}
			function stopPolling() {
				if (state.pollingInterval) {
					clearInterval(state.pollingInterval);
					state.pollingInterval = null;
				}
			}
			if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
			else init();
			const WidgetAPI = {
				init,
				open,
				close,
				toggle,
				setMessage,
				version: WIDGET_VERSION,
				on(event, callback) {
					window.addEventListener(`widget:${event}`, (e) => callback(e.detail));
					return this;
				},
				off(event, callback) {
					window.removeEventListener(`widget:${event}`, callback);
					return this;
				}
			};
			global.ChatWidget = WidgetAPI;
			if (typeof module !== "undefined" && module.exports) module.exports = WidgetAPI;
		})(window);
	})))();
})();
