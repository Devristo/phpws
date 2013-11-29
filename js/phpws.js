/**
 * Created by Chris on 29-11-13.
 */

!function($){
    var Client = function(url){
        var self = this;
        var ws = new WebSocket(url);
        var openPromise = $.Deferred();

        ws.addEventListener('open', function(){
            openPromise.resolveWith(self);
        });

        ws.addEventListener('error', function(){
            openPromise.rejectWith(self);
        });

        this.unsubscribe = function(){
            ws.removeEventListener.apply(ws, arguments);
            return this;
        };

        this.on = function(){
            ws.addEventListener.apply(ws, arguments);
            return this;
        };

        this.open = function(){
            return openPromise;
        };

        this.send = function(data){
            return ws.send(data);
        };

        this.close = function(){
            return ws.close.apply(ws, arguments);
        }
    };

    var JsonMessage = function(sender, tag, data){
        this.getData = function () {
            return data;
        };

        this.reply = function(data){
            var msg = {
                tag: tag,
                data: data
            };

            sender(msg);
        };
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

        phpws.on("message", function(message){
            var messageObj = JSON.parse(message.data);
            var jsonMessage = new JsonMessage(sendObj, messageObj.tag, messageObj.data);

            self.emit("message", jsonMessage);
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

        this.emit = function(event){
            if(event in callbacks){
                callbacks[event].fireWith(this, Array.prototype.slice.call(arguments, 1));
            }

            return this;
        };

        this.send = function(data){
            var msg = {
                tag: generateTag(),
                data: data
            };

            sendObj(msg);
        }
    };

    window.Phpws = {
        Client: Client,
        JsonTransport: JsonTransport
    };

}(window.jQuery);