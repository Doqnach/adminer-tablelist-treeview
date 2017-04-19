<?php
    /**
     * Adminer plugin to combine tables in collapsible groups based on the names
	 *
     * @author Doqnach, https://github.com/Doqnach
     * @copyright 2017, Miraizou.Net: Vision of the Future
	 *
     * @license https://opensource.org/licenses/MIT, The MIT License (MIT)
	 *
     * @link https://www.adminer.org/plugins/#use
	 * @link https://github.com/Doqnach
     */

    /**
     * TreeView-ish Plugin Class
     */
    class AdminerTableListTreeView
    {
    	/** @var int */
        private $_threshold;
        /** @var int */
        private $_depth;
        /** @var int */
        private $_skip;
        /** @var string */
        private $_split;

        /**
         * @param int $threshold if < 2 will get set to 2
         * @param int $depth 0 = unlimited, >0 the depth of grouping (1 = no effect)
         * @param int $skip first group levels to skip, 0 = no skipping
		 * @param string $split regex defining the token divider, defaults to 'before one or more underscores', needs to be zero-width
         */
        public function __construct($threshold = 5, $depth = 3, $skip = 0, $split = /** @lang RegExp */ '/(?<!_)(?=_+)/')
        {
            $this->_threshold = $threshold < 2 ? 2 : $threshold;
            $this->_depth = $depth < 0 ? 0 : $depth;
            $this->_skip = $skip < 0 ? 0 : $skip;
            $this->_split = $split;
        }

        /** Prints table list in menu
         *
         * To improve on: recursive, remember status
         *
         * @param array $tables {
         *   @var string $Name
         *   @var string|null $Engine
         *   @var string $Comment
         * } array result of table_status('', true)
         * @return bool
         */
        public function tablesPrint($tables)
        {
        	$self = adminer();
        	$db = $self->database();

            $groups = array();
            foreach($tables as $key => $table) {
                $name = $self->tableName($table);
                if ($this->_depth === 1) {
                	$groups[strtolower($name)] = array('*self' => 1);
				} else {
					$group = array_map('strtolower',preg_split($this->_split, $name, -1, PREG_SPLIT_NO_EMPTY));
					if ($this->_skip > 0) {
						$group = array_merge(array(implode('', array_splice($group, 0, $this->_skip + 1))), $group);
					}
					if ($this->_depth > 0 && count($group) > $this->_depth) {
						$group = array_merge(array_splice($group, 0, $this->_depth - 1), count($group) > 0 ? array(implode('', $group)) : array());
					}
					$group = array_map('strtolower', $group);
					$in = &$groups;
					while($section = array_shift($group)) {
						if (false === array_key_exists($section, $in)) {
							$in[$section] = array();
						}
						if (count($group) === 0) {
							$in[$section]['*self'] = 1;
						}
						$in = &$in[$section];
					}
				}
            }
//var_dump('<pre>', $groups, '</pre>');

			?>
		  		<style type="text/css">
					.adminertablelisttreeview_toggle::before {
						content: '[-] ';
						font-family: monospace;
					}
					.adminertablelisttreeview_toggle + br + .adminertablelisttreeview_group {
						display: block;
					}
					.adminertablelisttreeview_toggle_closed::before {
						content: '[+] ';
						font-family: monospace;
					}
					.adminertablelisttreeview_toggle_closed + br + .adminertablelisttreeview_group {
						display: none;
					}
					.adminertablelisttreeview_group {
						padding-left: .5em;
					}
				</style>
			<?php
            print("<p id='tables' onmouseover='menuOver(this, event);' onmouseout='menuOut(this);'>\n");
            $index = 0;
            $skip = 0;
            foreach ($tables as $key => $table) {
            	if (--$skip > 0) {
            		continue;
				}
                $name = $self->tableName($table);

                $group = preg_split($this->_split, $name, -1, PREG_SPLIT_NO_EMPTY);
                if ($this->_skip > 0) {
                    $group = array_merge(array(implode('', array_splice($group, 0, $this->_skip + 1))), $group);
                }
                if ($this->_depth > 0 && count($group) > $this->_depth) {
                    $group = array_merge(array_splice($group, 0, $this->_depth - 1), count($group) > 0 ? array(implode('', $group)) : array());
                }
//var_dump('<pre>', $table, $name, $group, '</pre>');

                $count = 0;
                $curgroup = strtolower($group[0]);
                if (true === array_key_exists($curgroup, $groups) && true === is_array($groups[$curgroup])) {
                    $count = self::_arraySumRecursive($groups[$curgroup]);
                }

                if ($count > $this->_threshold) {
                	print('<a class="adminertablelisttreeview_toggle adminertablelisttreeview_toggle_closed" href="#" id="tablelist.' . $db . '.' . h($curgroup) . '" data-adminertablelisttreeview-group="'.h($curgroup).'">' . h($group[0]) . '</a><br />');
                	$this->_tablesPrintSub(array_slice($tables, $index, $count), $index, 1);
                	$skip = $count;
                	continue;
				} else {
					echo '<a href="' . h(ME) . 'select=' . urlencode($key) . '"' . bold($_GET["select"] == $key || $_GET["edit"] == $key, "select") . ">" . 'select' . "</a> ";
					echo (support("table") || support("indexes") ? '<a href="' . h(ME) . 'table=' . urlencode($key) . '"' . bold(in_array($key,
						  array($_GET["table"],$_GET["create"],$_GET["indexes"],$_GET["foreign"],$_GET["trigger"])),
						  (is_view($table) ? "view" : ""),
						  "structure") . " title='" . 'Show structure' . "'>$name</a>" : "<span>$name</span>") . "<br>\n";
                	$index++;
				}
            }

            print('</p>');
            ?>
                <script type="text/javascript">
                    (function(){
                    	'use strict';

                    	var AdminerTableListTreeView = (function(db) {
                    		var _storage = window.localStorage;

							var _data = function (ele, name) {
								var key = 'adminertablelisttreeview' + name.charAt(0).toUpperCase() + name.slice(1);
								if (ele.dataset.hasOwnProperty(key)) {
									return ele.dataset[key];
								} else {
									return null;
								}
							};

							var _store = function(ele, state) {
								var group = db + '.' + _data(ele, 'group');
								if (group.length > 0) {
									_groups[group] = state;
									_storage.setItem('adminertablelisttreeview_groups', JSON.stringify(_groups));
								}
							};

							var _methods = {
								open: function(ele) {
									if (ele.classList.contains('adminertablelisttreeview_toggle_closed')) {
										ele.classList.remove('adminertablelisttreeview_toggle_closed');
										_store(ele, true);
									}
								},
								close: function(ele) {
									if (!ele.classList.contains('adminertablelisttreeview_toggle_closed')) {
										ele.classList.add('adminertablelisttreeview_toggle_closed');
										_store(ele, false);
									}
								},
								toggle: function(ele) {
									ele.classList.toggle('adminertablelisttreeview_toggle_closed');
									_store(ele, !ele.classList.contains('adminertablelisttreeview_toggle_closed'));
								}
							};

                    		var _groups = {};
                    		if (_storage.getItem('adminertablelisttreeview_groups')) {
                    			try {
									_groups = JSON.parse(_storage.getItem('adminertablelisttreeview_groups'));
								} catch(e) {
                    				_groups = {}
								}
							}

							// init states
							for (i in _groups) {
                    			if (_groups.hasOwnProperty(i)) {
                    				if (_groups[i]) {
                    					var ele = document.getElementById('tablelist.' + i);
                    					if (ele) {
                    						_methods.open(ele);
										}
									}
								}
							}

                    		return _methods;
						})('<?=$db?>');

                    	var elements = document.getElementsByClassName('adminertablelisttreeview_toggle');
                    	for (var i in elements) {
                    		if (elements.hasOwnProperty(i)) {
								var ele = elements[i];
								ele.onclick = function() {
									AdminerTableListTreeView.toggle(this);
									return false;
								};
							}
						}
                    })();
                </script>
            <?php

            return true;
        }

        private function _tablesPrintSub($tables, &$index, $depth)
		{
			$self = adminer();
// TODO recursion
			print('<span class="adminertablelisttreeview_group">');
            foreach ($tables as $key => $table) {
                $name = $self->tableName($table);

                echo '<a href="' . h(ME) . 'select=' . urlencode($key) . '"' . bold($_GET["select"] == $key || $_GET["edit"] == $key, "select") . ">" . 'select' . "</a> ";
				echo (support("table") || support("indexes") ? '<a href="' . h(ME) . 'table=' . urlencode($key) . '"' . bold(in_array($key,
					  array($_GET["table"],$_GET["create"],$_GET["indexes"],$_GET["foreign"],$_GET["trigger"])),
					  (is_view($table) ? "view" : ""),
					  "structure") . " title='" . 'Show structure' . "'>$name</a>" : "<span>$name</span>") . "<br>\n";

				$index++;
			}
			print('</span>');
		}

        private static function _arraySumRecursive(array $array)
		{
			$total = 0;
			foreach($array as $item) {
				if (true === is_array($item)) {
					$total += self::_arraySumRecursive($item);
				} elseif (true === is_numeric($item)) {
					$total += $item;
				}
			}
			return $total;
		}
    }
