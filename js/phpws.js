/**
 * Created by Chris on 29-11-13.
 */

!function($){
    var Emitter = function(){
        var callbacks = {};

        this.on = function(event, callback){
            if(!(event in callbacks))
                callbacks[event] = $.Callbacks();

            callbacks[event].add(callback);
            return this;
        };

        this.removeListener = function(event, callback){
            if(event in callbacks){
                callbacks[event].remove.apply(callbacks[event], Array.prototype.slice.call(arguments, 1));
            }

            return this;
        };

        this.once = function(event, callback){
            var self = this;
            var _once = function(){
                callback();
                self.removeListener(event, arguments.callee);
            };

            return this.on(event, _once);
        };

        this.trigger = function(event, data){
            if(event in callbacks){
                callbacks[event].fireWith(this, Array.prototype.slice.call(arguments, 1));
            }

            return this;
        };
    };

    var Client = function(){
        var self = this;
        var ws = null;
        var openPromise = $.Deferred();
        var reconnectTimer = null;

        this.connect = function(url, autoReconnect){
            ws = new WebSocket(url);

            ws.addEventListener('open', function(){
                openPromise.resolveWith(self);
                self.trigger("open", arguments);
            });

            ws.addEventListener('close', function(){
                openPromise.resolveWith(self);
                self.trigger("close", arguments);
            });

            ws.addEventListener('error', function(){
                openPromise.rejectWith(self);
                self.trigger("error", arguments);
            });

            ws.addEventListener('message', function(event){
                self.trigger("message", event.data);
            });


            if(autoReconnect && !reconnectTimer){
                reconnectTimer = setInterval(
                    function(){
                        if(!ws.readyState == WebSocket.OPEN)
                            self.connect(url);
                    },
                    5000
                );
            }

            return openPromise;
        };

        this.whenConnected = function(callback, context){
            this.on("open", function(){callback.call(context);});

            if(ws.readyState == WebSocket.OPEN){
                callback.call(context);
            }
        };

        this.send = function(data){
            return ws.send(data);
        };

        this.close = function(){
            return ws.close.apply(ws, arguments);
        }
    };

    Client.prototype = new Emitter();

    var Room = function(client, transport, name){
        var subscribed = false;

        this.on = function(event, callback){
            // Subscribe if we haven't done that yet
            if(!subscribed){
                throw Error("Not subscribed to room " + name);
            }

            this.constructor.prototype.on.apply(this, arguments);
            return this;
        };

        this.emit = function(event, data){
            transport.emit(name, event, data);
            return this;
        };

        this.subscribe = function(){
            client.whenConnected(function(){
                console.log("Subscribing to room " + name);
                transport.emit(name, "subscribe");
                subscribed = true;
            });

            return this;
        };
    };

    Room.prototype = new Emitter();

    var EventTransport = function(phpws){
        var self = this;
        var rooms = {};
        var previousTag = 0;
        var generateTag = function(){
            previousTag += 1;

            return "client-"+previousTag;
        };

        var sendObj = function(obj){
            phpws.send(JSON.stringify(obj));
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

        phpws.on("message", function(message){
            var messageObj = JSON.parse(message);
            addReply(messageObj);

            self.trigger(messageObj.event, messageObj);

            if('room' in messageObj){
                self.room(message.room).trigger(messageObj.event, messageObj);
            }
        });

        this.room = function(name){
            if(!(name in rooms))
                rooms[name] = new Room(phpws, this, name);

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
        }
    };

    EventTransport.prototype = new Emitter();

    window.Phpws = {
        Client: Client,
        RemoteEvents: EventTransport
    };

}(window.jQuery);