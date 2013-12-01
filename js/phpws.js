/**
 * Created by Chris on 29-11-13.
 */

!function($){
    var Client = function(url){
        var self = this;
        var ws = null;
        var openPromise = $.Deferred();
        var callbacks = {};

        var triggerEvent = function(event){
            if(event in callbacks){
                callbacks[event].fireWith(this, Array.prototype.slice.call(arguments, 1));
            }

            return this;
        };


        this.on = function(event, callback){
            if(!(event in callbacks))
                callbacks[event] = $.Callbacks();

            callbacks[event].add.apply(callbacks[event], Array.prototype.slice.call(arguments, 1));
            return this;
        };

        this.unsubscribe = function(event, callback){
            if(event in callbacks){
                callbacks[event].remove.apply(callbacks[event], Array.prototype.slice.call(arguments, 1));
            }

            return this;
        };

        this.connect = function(url){
            ws = new WebSocket(url);

            ws.addEventListener('open', function(){
                openPromise.resolveWith(self);
                triggerEvent("open", arguments);
            });

            ws.addEventListener('close', function(){
                openPromise.resolveWith(self);
                triggerEvent("close", arguments);
            });

            ws.addEventListener('error', function(){
                openPromise.rejectWith(self);
                triggerEvent("error", arguments);
            });

            ws.addEventListener('message', function(event){
                triggerEvent("message", event.data);
            });

            return openPromise;
        };

        this.send = function(data){
            return ws.send(data);
        };

        this.close = function(){
            return ws.close.apply(ws, arguments);
        }
    };

    var JsonTransport = function(phpws){
        var self = this;
        var callbacks = {};
        var previousTag = 0;
        var generateTag = function(){
            previousTag += 1;

            return "client-"+previousTag;
        };

        var sendObj = function(obj){
            phpws.send(JSON.stringify(obj));
        };

        var triggerEvent = function(event){
            if(event in callbacks){
                callbacks[event].fireWith(this, Array.prototype.slice.call(arguments, 1));
            }

            return this;
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


            triggerEvent(messageObj.event, messageObj);
        });

        this.on = function(event, callback){
            if(!(event in callbacks))
                callbacks[event] = $.Callbacks();

            callbacks[event].add.apply(callbacks[event], Array.prototype.slice.call(arguments, 1));
            return this;
        };

        this.unsubscribe = function(event, callback){
            if(event in callbacks){
                callbacks[event].remove.apply(callbacks[event], Array.prototype.slice.call(arguments, 1));
            }

            return this;
        };

        this.emit = function(room, event, args){
            var msg = {
                room: room,
                tag: generateTag(),
                event: event,
                data: args
            };

            sendObj(msg);
        }
    };

    window.Phpws = {
        Client: Client,
        RemoteEvents: JsonTransport
    };

}(window.jQuery);