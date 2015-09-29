(function() {
    xxtApp.register.controller('recordCtrl', ['$scope', 'http2', '$modal', function($scope, http2, $modal) {
        $scope.$parent.subView = 'record';
        $scope.notifyMatterTypes = [{
            value: 'text',
            title: '文本',
            url: '/rest/mp/matter'
        }, {
            value: 'article',
            title: '单图文',
            url: '/rest/mp/matter'
        }, {
            value: 'news',
            title: '多图文',
            url: '/rest/mp/matter'
        }, {
            value: 'channel',
            title: '频道',
            url: '/rest/mp/matter'
        }, {
            value: 'enroll',
            title: '登记活动',
            url: '/rest/mp/app'
        }, ];
        $scope.doSearch = function(page) {
            var url;
            page && ($scope.page.at = page);
            url = '/rest/mp/app/enroll/record/get';
            url += '?aid=' + $scope.aid;
            url += '&tags=' + $scope.page.tags.join(',');
            url += $scope.page.joinParams();
            http2.get(url, function(rsp) {
                var i, j, r;
                if (rsp.data) {
                    $scope.records = rsp.data.records ? rsp.data.records : [];
                    rsp.data.total && ($scope.page.total = rsp.data.total);
                    rsp.data.schema && ($scope.cols = rsp.data.schema);
                } else
                    $scope.records = [];
                for (i = 0, j = $scope.records.length; i < j; i++) {
                    r = $scope.records[i];
                    r.data.member && (r.data.member = JSON.parse(r.data.member));
                }
            });
        };
        $scope.page = {
            at: 1,
            size: 30,
            keyword: '',
            tags: [],
            searchBy: 'nickname',
            orderBy: 'time',
            joinParams: function() {
                var p;
                p = '&page=' + this.at + '&size=' + this.size;
                if (this.keyword !== '') {
                    p += '&kw=' + this.keyword;
                    p += '&by=' + this.searchBy;
                }
                p += '&orderby=' + this.orderBy;
                p += '&rid=' + (this.byRound ? this.byRound : 'ALL');
                return p;
            }
        };
        $scope.searchBys = [{
            n: '昵称',
            v: 'nickname'
        }];
        $scope.orderBys = [{
            n: '登记时间',
            v: 'time'
        }, {
            n: '邀请数',
            v: 'follower'
        }, {
            n: '点赞数',
            v: 'score'
        }, {
            n: '评论数',
            v: 'remark'
        }];
        $scope.selected = {};
        $scope.selectAll;
        $scope.$on('search-tag.xxt.combox.done', function(event, aSelected) {
            $scope.page.tags = $scope.page.tags.concat(aSelected);
            $scope.doSearch();
        });
        $scope.$on('search-tag.xxt.combox.del', function(event, removed) {
            var i = $scope.page.tags.indexOf(removed);
            $scope.page.tags.splice(i, 1);
            $scope.doSearch();
        });
        $scope.$on('batch-tag.xxt.combox.done', function(event, aSelected) {
            var i, record, records = [],
                eks = [],
                posted;
            for (i in $scope.selected) {
                if ($scope.selected) {
                    record = $scope.records[i];
                    eks.push(record.enroll_key);
                    records.push(record);
                }
            }
            if (eks.length) {
                posted = {
                    eks: eks,
                    tags: aSelected
                };
                http2.post('/rest/mp/app/enroll/record/batchTag?aid=' + $scope.aid, posted, function(rsp) {
                    var i, l, m, n, newTag;
                    n = aSelected.length;
                    for (i = 0, l = records.length; i < l; i++) {
                        record = records[i];
                        if (!record.tags || record.length === 0) {
                            record.tags = aSelected.join(',');
                        } else
                            for (m = 0; m < n; m++) {
                                newTag = aSelected[m];
                                (',' + record.tags + ',').indexOf(newTag) === -1 && (record.tags += ',' + newTag);
                            }
                    }
                });
            }
        });
        $scope.$on('pushnotify.xxt.done', function(event, matter) {
            var url = '/rest/mp/app/enroll/record/sendNotify';
            url += '?matterType=' + matter[1];
            url += '&matterId=' + matter[0][0].id;
            url += '&aid=' + $scope.aid;
            url += '&tags=' + $scope.page.tags.join(',');
            url += $scope.page.joinParams();
            http2.get(url, function(data) {
                $scope.$root.infomsg = '发送成功';
            });
        });
        $scope.viewUser = function(fan) {
            location.href = '/rest/mp/user?openid=' + fan.openid;
        };
        $scope.keywordKeyup = function(evt) {
            evt.which === 13 && $scope.doSearch();
        };
        $scope.memberAttr = function(val, key) {
            var keys;
            if (val.member) {
                keys = key.split('.');
                if (keys.length === 2) {
                    return val.member[keys[1]];
                } else if (val.member.extattr) {
                    return val.member.extattr[keys[2]];
                } else {
                    return '';
                }
            } else {
                return '';
            }
        };
        $scope.value2Label = function(val, key) {
            var i, j, s, aVal, aLab = [];
            if (val === undefined) return '';
            for (i = 0, j = $scope.cols.length; i < j; i++) {
                s = $scope.cols[i];
                if ($scope.cols[i].id === key) {
                    s = $scope.cols[i];
                    break;
                }
            }
            if (s && s.ops && s.ops.length) {
                aVal = val.split(',');
                for (i = 0, j = s.ops.length; i < j; i++) {
                    aVal.indexOf(s.ops[i].v) !== -1 && aLab.push(s.ops[i].label);
                }
                if (aLab.length) return aLab.join(',');
            }
            return val;
        };
        $scope.json2Obj = function(json) {
            if (json && json.length) {
                obj = JSON.parse(json);
                return obj;
            } else {
                return {};
            }
        };
        $scope.editRecord = function(record) {
            $modal.open({
                templateUrl: 'editor.html',
                controller: 'editorCtrl',
                windowClass: 'auto-height',
                resolve: {
                    enroll: function() {
                        return $scope.editing;
                    },
                    record: function() {
                        record.aid = $scope.aid;
                        return record;
                    },
                    cols: function() {
                        return $scope.cols;
                    }
                }
            }).result.then(function(updated) {
                var p = updated[0],
                    tags = updated[1];
                $scope.editing.tags = tags;
                $scope.update('tags');
                http2.post('/rest/mp/app/enroll/record/update?aid=' + $scope.aid + '&ek=' + record.enroll_key, p);
            });
        };
        $scope.addRecord = function() {
            $modal.open({
                templateUrl: 'editor.html',
                controller: 'editorCtrl',
                windowClass: 'auto-height',
                resolve: {
                    enroll: function() {
                        return $scope.editing;
                    },
                    record: function() {
                        return {
                            aid: $scope.aid,
                            tags: ''
                        };
                    },
                    cols: function() {
                        return $scope.cols;
                    }
                }
            }).result.then(function(updated) {
                var p = updated[0],
                    tags = updated[1];
                if ($scope.editing.tags.length !== tags.length) {
                    $scope.editing.tags = tags;
                    $scope.update('tags');
                }
                http2.post('/rest/mp/app/enroll/record/add?aid=' + $scope.aid, p, function(rsp) {
                    $scope.records.splice(0, 0, rsp.data);
                });
            });
        };
        $scope.importUser = function() {
            $modal.open({
                templateUrl: "userPicker.html",
                backdrop: 'static',
                windowClass: 'auto-height',
                size: 'lg',
                controller: function($scope, $modalInstance) {
                    $scope.cancel = function() {
                        $modalInstance.dismiss();
                    }
                },
            }).result.then(function(selected) {
                if (selected.members && selected.members.length) {
                    var members = [];
                    for (var i in selected.members)
                        members.push(selected.members[i].data.mid);
                    http2.post('/rest/mp/app/record/importUser?aid=' + $scope.aid, members, function(rsp) {
                        for (var i in rsp.data)
                            $scope.records.splice(0, 0, rsp.data[i]);
                    });
                }
            });
        };
        $scope.importApp = function() {
            $modal.open({
                templateUrl: 'importApp.html',
                controller: 'importAppCtrl',
                backdrop: 'static',
                size: 'lg'
            }).result.then(function(param) {
                http2.post('/rest/mp/app/enroll/record/importApp?aid=' + $scope.aid, param, function(rsp) {
                    $scope.doSearch(1);
                });
            });
        };
        $scope.removeRecord = function(record) {
            if (window.confirm('确认删除？')) {
                http2.get('/rest/mp/app/enroll/record/remove?aid=' + $scope.aid + '&key=' + record.enroll_key, function(rsp) {
                    var i = $scope.records.indexOf(record);
                    $scope.records.splice(i, 1);
                    $scope.page.total = $scope.page.total - 1;
                });
            }
        };
        $scope.empty = function() {
            var vcode;
            vcode = prompt('是否要删除所有登记信息？，若是，请输入活动名称。');
            if (vcode === $scope.editing.title) {
                http2.get('/rest/mp/app/enroll/record/empty?aid=' + $scope.aid, function(rsp) {
                    $scope.doSearch(1);
                });
            }
        };
        $scope.$watch('selectAll', function(nv) {
            var i, j;
            if (nv !== undefined)
                for (i = 0, j = $scope.records.length; i < j; i++) {
                    $scope.selected[i] = nv;
                }
        });
        $scope.doSearch();
    }]);
    xxtApp.register.controller('importAppCtrl', ['$scope', 'http2', '$modalInstance', function($scope, http2, $modalInstance) {
        $scope.param = {
            checkedActs: [],
            checkedWalls: [],
            wallUserState: 'active',
            alg: 'inter'
        };
        $scope.changeAct = function(act) {
            var i = $scope.param.checkedActs.indexOf(act.aid);
            if (i === -1)
                $scope.param.checkedActs.push(act.aid);
            else
                $scope.param.checkedActs.splice(i, 1);
        };
        $scope.changeWall = function(wall) {
            var i = $scope.param.checkedWalls.indexOf(wall.wid);
            if (i === -1)
                $scope.param.checkedWalls.push(wall.wid);
            else
                $scope.param.checkedWalls.splice(i, 1);
        };
        $scope.cancel = function() {
            $modalInstance.dismiss();
        };
        $scope.ok = function() {
            $modalInstance.close($scope.param);
        };
        http2.get('/rest/mp/app/enroll/get?page=1&size=999', function(rsp) {
            $scope.activities = rsp.data[0];
        });
        http2.get('/rest/mp/app/wall/get', function(rsp) {
            $scope.walls = rsp.data;
        });
    }]);
    xxtApp.register.controller('editorCtrl', ['$scope', '$modalInstance', 'enroll', 'record', 'cols', function($scope, $modalInstance, enroll, record, cols) {
        $scope.enroll = enroll;
        $scope.record = record;
        $scope.record.aTags = (!record.tags || record.tags.length === 0) ? [] : record.tags.split(',');
        $scope.aTags = enroll.tags;
        $scope.cols = cols;
        $scope.json2Obj = function(json) {
            if (json && json.length) {
                obj = JSON.parse(json);
                return obj;
            } else {
                return {};
            }
        };
        $scope.signin = function() {
            $scope.record.signin_at = Math.round((new Date()).getTime() / 1000);
        };
        $scope.ok = function() {
            var p, col;
            p = {
                tags: $scope.record.aTags.join(','),
                data: {}
            };
            $scope.record.tags = p.tags;
            if ($scope.record.id)
                p.signin_at = $scope.record.signin_at;
            for (var c in $scope.cols) {
                col = $scope.cols[c];
                p.data[col.id] = $scope.record.data[col.id];
            }
            $modalInstance.close([p, $scope.aTags]);
        };
        $scope.cancel = function() {
            $modalInstance.dismiss('cancel');
        };
        $scope.$on('tag.xxt.combox.done', function(event, aSelected) {
            var aNewTags = [];
            for (var i in aSelected) {
                var existing = false;
                for (var j in $scope.record.aTags) {
                    if (aSelected[i] === $scope.record.aTags[j]) {
                        existing = true;
                        break;
                    }
                }!existing && aNewTags.push(aSelected[i]);
            }
            $scope.record.aTags = $scope.record.aTags.concat(aNewTags);
        });
        $scope.$on('tag.xxt.combox.add', function(event, newTag) {
            $scope.record.aTags.push(newTag);
            $scope.aTags.indexOf(newTag) === -1 && $scope.aTags.push(newTag);
        });
        $scope.$on('tag.xxt.combox.del', function(event, removed) {
            $scope.record.aTags.splice($scope.record.aTags.indexOf(removed), 1);
        });
    }]);
})();