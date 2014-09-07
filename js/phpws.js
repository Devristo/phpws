/**
 * Created by Chris on 29-11-13.
 */

!function($){
    function Emitter() {
        this.callbacks = {};
    }

    Emitter.prototype.on = function(event, callback){
        if(!(event in this.callbacks))
            this.callbacks[event] = $.Callbacks();

        this.callbacks[event].add(callback);
        return this;
    };

    Emitter.prototype.removeListener = function(event, callback){
        if(event in this.callbacks){
            this.callbacks[event].remove.apply(this.callbacks[event], Array.prototype.slice.call(arguments, 1));
        }

        return this;
    };

    Emitter.prototype.once = function(event, callback){
        var self = this;
        var _once = function(){
            callback();
            self.removeListener(event, arguments.callee);
        };

        return this.on(event, _once);
    };

    Emitter.prototype.trigger = function(event, data){
        if(event in this.callbacks){
            this.callbacks[event].fireWith(this, Array.prototype.slice.call(arguments, 1));
        }

        return this;
    };

    function Client(){
        Emitter.apply(this, arguments);

        var self = this;
        var ws = null;
        var openPromise = $.Deferred();
        var reconnectTimer = null;

        this.connect = function(url, autoReconnect){
            try {
                ws = new WebSocket(url);
            }catch(error){
                console.log("Cannot create WebSocket instance");
                return openPromise;
            }

            ws.addEventListener('open', function () {
                openPromise.resolveWith(self);
                self.trigger("open", arguments);
            });

            ws.addEventListener('close', function () {
                openPromise.resolveWith(self);
                self.trigger("close", arguments);
            });

            ws.addEventListener('error', function () {
                openPromise.rejectWith(self);
                self.trigger("error", arguments);
            });

            ws.addEventListener('message', function (event) {
                self.trigger("message", [event.data]);
            });

            if (autoReconnect && !reconnectTimer) {
                reconnectTimer = setInterval(
                    function () {
                        if (ws.readyState != WebSocket.OPEN)
                            self.connect(url);
                    },
                    5000
                );
            }

            return openPromise;
        };

        this.whenConnected = function(callback, context){
            this.on("open", function(){callback.call(context);});

            if(ws && ws.readyState == WebSocket.OPEN){
                callback.call(context);
            }
        };

        this.send = function(data){
            return ws.send(data);
        };

        this.close = function(){
            return ws.close.apply(ws, arguments);
        };
    };

    Client.prototype = new Emitter;
    Client.prototype.constructor = Client;

    function Room (client, transport, name){
        Emitter.apply(this, arguments);

        this.subscribed = true;
        this.name = name;
        this.transport = transport;
        this.client = client;
    }

    Room.prototype = new Emitter;
    Room.prototype.constructor = Room;

    Room.prototype.on = function(event, callback){
        // Subscribe if we haven't done that yet
        if(!this.subscribed){
            throw Error("Not subscribed to room " + this.name);
        }

        Emitter.prototype.on.apply(this, arguments);
        return this;
    };

    Room.prototype.emit = function(event, data){
        this.transport.emit(this.name, event, data);
        return this;
    };

    Room.prototype.subscribe = function(){
        var self = this;
        this.subscribed = true;
        this.client.whenConnected(function(){
            console.log("Subscribing to room " + self.name);
            self.transport.emit(self.name, "subscribe");
        });
        return this;
    };

    function EventTransport (client){
        Emitter.apply(this, arguments);

        var self = this;
        var rooms = {};
        var previousTag = 0;
        var generateTag = function(){
            previousTag += 1;
            return "client-"+previousTag;
        };

        var sendObj = function(obj){
            client.send(JSON.stringify(obj));
        };

        var addReply = function(obj){
            obj.reply = function(data){
                var msg = {
                    tag: obj.tag,
                    data: obj.data,
                    event: obj.event,
                    room: obj.room
                };

                sendObj(msg);
            };
        };

        client.on("message", function(message){
            var messageObj = JSON.parse(message);
            addReply(messageObj);

            self.trigger(messageObj.event, messageObj);

            if('room' in messageObj){
                self.room(messageObj.room).trigger(messageObj.event, messageObj);
            }
        });

        this.room = function(name){
            if(!(name in rooms))
                rooms[name] = new Room(client, this, name);

            return rooms[name];
        };

        this.emit = function(room, event, args){
            var msg = {
                room: room,
                tag: generateTag(),
                event: event,
                data: args
            };

            sendObj(msg);

            return this;
        };
    };

    EventTransport.prototype = new Emitter;
    EventTransport.prototype.constructor = EventTransport;

    window.Phpws = {
        Client: Client,
        RemoteEvents: EventTransport
    };

}(window.jQuery);
