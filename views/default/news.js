if (/MicroMessenger/.test(navigator.userAgent)) {
    //signPackage.debug = true;
    signPackage.jsApiList = ['hideOptionMenu', 'onMenuShareTimeline', 'onMenuShareAppMessage'];
    wx.config(signPackage);
}
angular.module('xxt', []).config(['$locationProvider', function ($locationProvider) {
    $locationProvider.html5Mode(true);
}]).controller('ctrl', ['$scope', '$location', '$http', '$q', function ($scope, $location, $http, $q) {
    var mpid, newsId, shareby;
    mpid = $location.search().mpid;
    newsId = $location.search().id;
    shareby = $location.search().shareby ? $location.search().shareby : '';
    var setShare = function () {
        var shareid, sharelink;
        shareid = $scope.user.vid + (new Date()).getTime();
        window.xxt.share.options.logger = function (shareto) {
            var url = "/rest/mi/matter/logShare";
            url += "?shareid=" + shareid;
            url += "&mpid=" + mpid;
            url += "&id=" + newsId;
            url += "&type=news";
            url += "&title=" + $scope.news.title;
            url += "&shareto=" + shareto;
            url += "&shareby=" + shareby;
            $http.get(url);
        };
        sharelink = location.href;
        if (/shareby=/.test(sharelink))
            sharelink = sharelink.replace(/shareby=[^&]*/, 'shareby=' + shareid);
        else
            sharelink += "&shareby=" + shareid;
        window.xxt.share.set($scope.news.title, sharelink, $scope.news.title, '');
    };
    var getNews = function () {
        var deferred = $q.defer();
        $http.get('/rest/mi/news/get?mpid=' + mpid + '&id=' + newsId).success(function (rsp) {
            var news;
            news = rsp.data.news;
            if (news.matters && news.matters.length === 1) {
                $http.get('/rest/mi/matter/logAccess?mpid=' + mpid + '&id=' + newsId + '&type=news&title=' + rsp.data.title + '&shareby=' + shareby);
                location.href = news.matters[0].url;
            } else {
                $scope.user = rsp.data.user;
                $scope.news = news;
                if (/MicroMessenge|Yixin/i.test(navigator.userAgent)) {
                    setShare();
                }
                deferred.resolve();
                $http.get('/rest/mi/matter/logAccess?mpid=' + mpid + '&id=' + newsId + '&type=news&title=' + $scope.news.title + '&shareby=' + shareby);
            }
        }).error(function (content, httpCode) {
            if (httpCode === 401) {
                var el = document.createElement('iframe');
                el.setAttribute('id', 'frmAuth');
                el.onload = function () { this.height = document.documentElement.clientHeight; };
                document.body.appendChild(el);
                if (content.indexOf('http') === 0) {
                    window.onAuthSuccess = function () {
                        el.style.display = 'none';
                        getNews().then(function () { $scope.loading = false; });
                    };
                    el.setAttribute('src', content);
                    el.style.display = 'block';
                } else {
                    if (el.contentDocument && el.contentDocument.body) {
                        el.contentDocument.body.innerHTML = content;
                        el.style.display = 'block';
                    }
                }
            } else {
                alert(content);
            }
        });
        return deferred.promise;
    };
    $scope.open = function (opened) {
        location.href = opened.url;
    };
    $scope.loading = true;
    getNews().then(function () { $scope.loading = false; });
}]);
